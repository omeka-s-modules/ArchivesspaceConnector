<?php
namespace ArchivesspaceConnector\Job;

use Omeka\Job\AbstractJob;
use Omeka\Entity\ItemSet;
use Omeka\Api\Exception\NotFoundException;

class Import extends AbstractJob
{
    protected $client;

    protected $propertyUriIdMap;

    protected $api;
    
    protected $logger;

    protected $itemSetArray;

    protected $itemSites;

    protected $addedCount;

    protected $updatedCount;
    
    protected $resourceTemplateId;

    protected $hierarchyId;

    protected $deletedCount;

    public function perform()
    {
        $this->api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $this->logger = $this->getServiceLocator()->get('Omeka\Logger');
        $comment = $this->getArg('comment');
        $this->rerun = $this->getArg('rerun');
        $archivesspaceImportJson = [
                            'o:job' => ['o:id' => $this->job->getId()],
                            'comment' => $comment,
                            'added_count' => 0,
                            'updated_count' => 0,
                            'hierarchy_id' => 0,
                          ];
        $response = $this->api->create('archivesspace_imports', $archivesspaceImportJson);
        $importRecordId = $response->getContent()->id();
        
        // Build dcterms metadata field map
        $this->fieldIdMap = [];
        $this->prefix = 'dcterms';
        $this->namespace = 'http://purl.org/dc/terms/';

        $properties = $this->api->search('properties', [
            'vocabulary_namespace_uri' => $this->namespace,
        ])->getContent();

        foreach ($properties as $property) {
            $field = $property->localName();
            $this->fieldIdMap[$field] = $property->id();
        }

        $this->addedCount = 0;
        $this->updatedCount = 0;
        $this->client = $this->getServiceLocator()->get('Omeka\HttpClient');
        $this->itemSiteArray = $this->getArg('itemSites', false);
        $this->previousItemsetArray = $this->getArg('previous_itemset_array', false) ?: [];
        $this->resourceTemplateId = (int) $this->getArg('resource_template', 0);
        
        if ($this->getArg('aspace_api_url') && $this->getArg('aspace_target_path')) {
            $this->apiUrl = trim($this->getArg('aspace_api_url'), '/');
            $targetPath = trim($this->getArg('aspace_target_path'), '/');
            $this->mainUri = $this->apiUrl . '/oai?verb=GetRecord&identifier=oai:archivesspace:/'
            . $targetPath . '&metadataPrefix=oai_ead';  
        } else {
            $this->mainUri = '';
        }
        
        // If maintain_hierarchy checked and hierarchy module not found, skip
        $container = $this->getServiceLocator();
        if ($this->getArg('maintain_hierarchy') && !$container->has(\Hierarchy\Service\HierarchyUpdater\HierarchyUpdater::class)
        ) {
            $this->logger->err("HierarchyUpdater service not found (is the Hierarchy module enabled?), skipping job.");
        } else {
            $this->importCollection($this->mainUri);
            if ($this->rerun && $this->getArg('delete_missing_items')) {
                // If delete_missing_items checked, delete any items
                // remaining from previous job (i.e. without updated job id)
                $remainingItems = $this->api->search('archivesspace_items', [
                    'job_id' => (int) $this->getArg('previous_job'),
                ]);
                foreach ($remainingItems->getContent() as $item) {
                    $this->api->delete('archivesspace_items', $item->id());
                    $this->api->delete('items', $item->item()->id());
                    $deletedCount++;
                }
                if ($deletedCount) {
                    $deletedComment = $deletedCount . ' items deleted';
                    $comment = strlen($comment) ? $comment . '; ' . $deletedComment : $deletedComment;
                }
            }
        }

        $archivesspaceImportJson = [
                            'o:job' => ['o:id' => $this->job->getId()],
                            'comment' => $comment,
                            'added_count' => $this->addedCount,
                            'updated_count' => $this->updatedCount,
                            'hierarchy_id' => $this->hierarchyId,
                          ];
        $response = $this->api->update('archivesspace_imports', $importRecordId, $archivesspaceImportJson);
    }

    public function importCollection($uri)
    {        
        $this->client->setUri($uri);
        try {
            $response = $this->client->send();
        } catch (\Exception $e) {
            $this->logger->err((string) $e);
        }
        if (!$response->isOK()) {
            $this->logger->err('HTTP problem: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
        }

        $body = $response->getBody();
        
        // Ensure valid xml
        $xml = @simplexml_load_string($body);
        if ($xml) {
            // Do some digging to register multiple nested XML namespaces without prefix
            $ns = $xml->getNamespaces(true);
            foreach ($ns as $prefix => $namespace) {
                if (empty($prefix)) {
                    $this->baseNs = $namespace;
                    $xml->registerXPathNamespace('ns', $this->baseNs);
                    $metadata = $xml->xpath("//ns:metadata");
                    if ($metadata) {
                        $mNamespaces = $metadata[0]->children()[0]->getNamespaces(true);
                        foreach ($mNamespaces as $mPrefix => $mNamespace) {
                            if (empty($mPrefix)) {
                                $this->eadNs = $mNamespace;
                            }
                        }
                    } else {
                        $this->eadNs = '';
                    }
                }
            }

            // Get top level resource XML object, URI & collection title
            $xml->registerXPathNamespace('ead_ns', $this->eadNs);
            $targetXML = $xml->xpath("//ead_ns:ead/ead_ns:archdesc");
            $collTitle = $xml->xpath("//ead_ns:ead/ead_ns:archdesc/ead_ns:did/ead_ns:unittitle");

            // Set collection name as job arg to link on past imports screen
            if (!empty($collTitle)) {
                $jobArgs = $this->job->getArgs();
                $jobArgs['collection_name'] = (string) $collTitle[0];
                $this->job->setArgs($jobArgs);
            }

            // Iterate through $targetXML saving nested resource URIs in array
            if (!empty($targetXML)) {
                $target = $targetXML[0];
                $target->registerXPathNamespace('ead_ns', $this->eadNs);

                $itemSet = null;
                // Loop through children/grandchildren and save as nested array
                $iterate = function ($XML) use (&$iterate, &$itemSet) {
                    $result = [];
                    foreach ($XML as $key => $childXML) {
                        $child = $childXML[0];
                        $child->registerXPathNamespace('ead_ns', $this->eadNs);

                        $nodeData = [];
                        $childUri = $child->xpath("./ead_ns:did/ead_ns:unitid[@type='aspace_uri']");
                        if (!empty($childUri)) {
                            $nodeData['uri'] = (string) $childUri[0];
                        }

                        // Check for children
                        $children = $child->xpath("./ead_ns:dsc/ead_ns:c | ./ead_ns:c");
                        if (!empty($children)) {
                            // If maintain_hierarchy checked, save AS collection/series/subseries/object structure as Hierarchy
                            if ($this->getArg('maintain_hierarchy') && isset($nodeData['uri'])) {
                                $seriesPath = trim($nodeData['uri'], '/');
                                // Create/Update item set
                                $itemSet = $this->createItemSet($seriesPath);
                                $itemSetLabel = $itemSet ? $itemSet->displayTitle() : '';

                                if ($this->rerun && $this->getArg('hierarchy_id')) {
                                    // If hierarchy grouping(s) already exist, delete and recreate on update to avoid duplication
                                    // or errors if another grouping in the hierarchy has since been assigned the same itemSet
                                    $existingGroupings = $this->api->search('hierarchy_grouping', [
                                        'hierarchy' => $this->getArg('hierarchy_id'),
                                    ]);
                                    foreach ($existingGroupings->getContent() as $grouping) {
                                        $this->api->delete('hierarchy_grouping', $grouping->id());
                                    }
                                }

                                $nodeData['text'] = $itemSetLabel;
                                $nodeData['data']['label'] = $itemSetLabel;
                                $nodeData['data']['itemSet'] = $itemSet ? $itemSet->id() : null;
                            } else {
                                $itemSet = null;
                            }
                            $nodeData['children'] = $iterate($children);
                            $result[] = $nodeData;
                        } else {
                            // If lowest level resource, import as item
                            $this->importTarget($nodeData['uri'], $itemSet);
                        }
                    }
                    return $result;
                };
                $asObjectArray = $iterate([$target])[0];
                
                // Build Hierarchy if maintain_hierarchy checked
                if ($this->getArg('maintain_hierarchy') && !empty($asObjectArray)) {
                    $hierarchyUpdater = $this->getServiceLocator()->get(\Hierarchy\Service\HierarchyUpdater\HierarchyUpdater::class);
                    $hierarchyData = [
                        'label' => isset($asObjectArray['text']) ? $asObjectArray['text'] : '',
                        'data' => '[' . json_encode($asObjectArray) . ']',
                        'id' => $this->getArg('hierarchy_id') ?: null,
                    ];
                    $this->hierarchyId = $hierarchyUpdater->updateHierarchy($hierarchyData);
                }
            }     
        }
    }

    /**
     * Import resource
     *
     * @param $uri
     * @param ItemSet $itemSet
     */
    public function importTarget($uri, $itemSet)
    {
        // See if the item has already been imported
        $response = $this->api->search('archivesspace_items', ['aspace_target_path' => $uri]);
        $content = $response->getContent();
        if (empty($content)) {
            $archivesspaceItem = false;
            $omekaItem = false;
        } else {
            $archivesspaceItem = $content[0];
            $omekaItem = $archivesspaceItem->item();
        }

        $uriPath = trim($uri, '/');
        $this->targetUri = $this->apiUrl . '/oai?verb=GetRecord&identifier=oai:archivesspace:/'
        . $uriPath . '&metadataPrefix=oai_dcterms'; 
        $this->client->setUri($this->targetUri);
        $response = $this->client->send();
        $body = $response->getBody();

        // Ensure valid xml
        $xml = @simplexml_load_string($body);
        if ($xml) {
            $json = $this->resourceToJson($xml);
        
            if ($omekaItem) {
                // keep existing item sites, add any new item sites
                $existingItem = $this->api->search('items', ['id' => $omekaItem->id()])->getContent();
            
                $existingItemSites = array_keys($existingItem[0]->sites()) ?: [];
                $newItemSites = $json['o:site'] ?: [];
                $json['o:site'] = array_merge($existingItemSites, $newItemSites);
                
                $existingItemSets = array_keys($existingItem[0]->itemSets()) ?: [];

                // Add newly created item set to existing item sets
                if (isset($itemSet) && !in_array($itemSet->id(), $existingItemSets)) {
                    $json['o:item_set'] = array_merge($existingItemSets, array($itemSet->id()));
                } else {
                    $json['o:item_set'] = $existingItemSets;
                }

                if ($this->resourceTemplateId) {
                    $json['o:resource_template']['o:id'] = (int) $this->resourceTemplateId;
                }
                
                try {
                    $response = $this->api->update('items', $omekaItem->id(), $json);
                    $itemId = $omekaItem->id();
                } catch (\Exception $e) {
                    $this->logger->err('Error importing resource with URI: ' . $uri); // @translate
                    $this->logger->err((string) $e);
                    // Update AS item job id so a previously created item doesn't get deleted if there is an issue with update
                    $this->api->update('archivesspace_items', $archivesspaceItem->id(), ['o:job' => ['o:id' => $this->job->getId()]]);
                    return;
                }
            } else {
                if (isset($itemSet)) {
                    $json['o:item_set'] = [ $itemSet->id() ];
                }

                if ($this->resourceTemplateId) {
                    $json['o:resource_template']['o:id'] = (int) $this->resourceTemplateId;
                }

                try {
                    $response = $this->api->create('items', $json);
                    $itemId = $response->getContent()->id();
                } catch (\Exception $e) {
                    $this->logger->err('Error importing resource with URI: ' . $uri); // @translate
                    $this->logger->err((string) $e);
                    return;
                }
            }
        
            // Get date last modified
            $xml->registerXPathNamespace('ns', $this->baseNs);
            $datestamp = $xml->xpath("//ns:header/ns:datestamp");
            if ($datestamp) {
                $date = (string) $datestamp[0];
                $lastModified = new \DateTime($date);
            } else {
                $lastModified = null;
            }
        
            $archivesspaceItemJson = [
                                'o:job' => ['o:id' => $this->job->getId()],
                                'o:item' => ['o:id' => $itemId],
                                'aspace_api_url' => $this->apiUrl,
                                'aspace_target_path' => $uri,
                                'last_modified' => $lastModified,
                              ];              
        
            if ($archivesspaceItem) {
                $response = $this->api->update('archivesspace_items', $archivesspaceItem->id(), $archivesspaceItemJson);
                $this->updatedCount++;
            } else {
                $this->addedCount++;
                $response = $this->api->create('archivesspace_items', $archivesspaceItemJson);
            }
        }
    }

    public function resourceToJson($xml)
    {
        $json = [];
        if ($this->itemSiteArray) {
            foreach ($this->itemSiteArray as $itemSite) {
                $itemSites[] = $itemSite;
            }
            $json['o:site'] = $itemSites;
        } else {
            $json['o:site'] = [];
        }
        
        // Get target dcterms XML metadata
        $xml->registerXPathNamespace($this->prefix, $this->namespace);
        $dcterms = $xml->xpath('//' . $this->prefix . ':*');
        if ($dcterms) {
            foreach ($dcterms as $dcterm) {
                $dcFieldname = $dcterm->getName();
                $dcValue = (string) $dcterm;

                if (isset($this->fieldIdMap[$dcFieldname])) {
                    $fieldId = $this->fieldIdMap[$dcFieldname];
                } else {
                    continue;
                }
                
                $valueArray = [];
                $valueArray['@value'] = $dcValue;
                $valueArray['type'] = 'literal';
                $valueArray['property_id'] = $fieldId;
                $json[$dcFieldname][] = $valueArray;
            }
            return $json;
        } else {
            return;
        }
    }
    
    protected function createItemSet($uri)
    {
        // See if the item set has already been imported
        $response = $this->api->search('archivesspace_item_sets', ['aspace_target_path' => $uri]);
        $content = $response->getContent();
        if (empty($content)) {
            $archivesspaceItemSet = false;
            $omekaItemSet = false;
        } else {
            $archivesspaceItemSet = $content[0];
            $omekaItemSet = $archivesspaceItemSet->itemSet();
        }
        
        // Get series API page for metadata
        $seriesPath = trim($uri, '/');
        $seriesUri = $this->apiUrl . '/oai?verb=GetRecord&identifier=oai:archivesspace:/'
        . $seriesPath . '&metadataPrefix=oai_dcterms';  
        $this->client->setUri($seriesUri);
        $seriesResponse = $this->client->send();
        $body = $seriesResponse->getBody();

        // Ensure valid xml
        $xml = @simplexml_load_string($body);
        if ($xml) {            
            // Get series dcterms XML metadata
            $xml->registerXPathNamespace($this->prefix, $this->namespace);
            $dcterms = $xml->xpath('//' . $this->prefix . ':*');
            if ($dcterms) {
                $itemSetData = [];
                foreach ($dcterms as $dcterm) {
                    if ($dcterm->getName() == 'title') {
                        $itemSetData['dcterms:title'] = [
                                ['@value' => (string) $dcterm,
                                'property_id' => $this->fieldIdMap['title'],
                                'type' => 'literal',
                                ], ];
                    }
                    if ($dcterm->getName() == 'description') {
                        $itemSetData['dcterms:description'] = [
                                ['@value' => (string) $dcterm,
                                'property_id' => $this->fieldIdMap['description'],
                                'type' => 'literal',
                                ], ];
                    }
                    if ($dcterm->getName() == 'rights') {
                        $itemSetData['dcterms:rights'] = [
                                ['@value' => (string) $dcterm,
                                'property_id' => $this->fieldIdMap['rights'],
                                'type' => 'literal',
                                ], ];
                    }
                }

                if ($omekaItemSet) {
                    $response = $this->api->update('item_sets', $omekaItemSet->id(), $itemSetData);
                    $itemSet = $response->getContent();
                    $itemSetId = $omekaItemSet->id();
                } else {
                    $response = $this->api->create('item_sets', $itemSetData);
                    $itemSet = $response->getContent();
                    $itemSetId = $itemSet->id();
                }
                
                // Keep existing siteItemSets, add new siteItemSet
                foreach ($this->itemSiteArray as $site) {
                    $siteItemSetsUpdate = [];
                    $siteEntity = $this->api->search('sites', ['id' => (int) $site])->getContent()[0];
                    foreach ($siteEntity->siteItemSets() as $siteItemSet) {
                        $siteItemSetsUpdate['o:site_item_set'][]['o:item_set']['o:id'] = $siteItemSet->itemSet()->id();
                    }
                    $siteItemSetsUpdate['o:site_item_set'][]['o:item_set']['o:id'] = $itemSetId;
                    $this->api->update('sites', $siteEntity->id(), $siteItemSetsUpdate, [], ['isPartial' => true]);
                }

                // Get date last modified
                $xml->registerXPathNamespace('ns', $this->baseNs);
                $datestamp = $xml->xpath("//ns:header/ns:datestamp");
                if ($datestamp) {
                    $date = (string) $datestamp[0];
                    $lastModified = new \DateTime($date);
                } else {
                    $lastModified = null;
                }

                $archivesspaceItemSetJson = [
                                    'o:job' => ['o:id' => $this->job->getId()],
                                    'o:item_set' => ['o:id' => $itemSetId],
                                    'aspace_api_url' => $this->apiUrl,
                                    'aspace_target_path' => $uri,
                                    'last_modified' => $lastModified,
                                  ];              
            
                if ($archivesspaceItemSet) {
                    $response = $this->api->update('archivesspace_item_sets', $archivesspaceItemSet->id(), $archivesspaceItemSetJson);
                } else {
                    $response = $this->api->create('archivesspace_item_sets', $archivesspaceItemSetJson);
                }
                return $itemSet;
            } else {
                return;
            }    
        } else {
            return;
        }
    }
}
