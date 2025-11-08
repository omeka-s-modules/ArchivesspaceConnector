<?php
namespace ArchivesspaceConnector\Job;

use Omeka\Job\AbstractJob;

class Undo extends AbstractJob
{
    protected $deletedCount;
    
    public function perform()
    {
        $jobId = $this->getArg('previous_job');
        $hierarchyId = $this->getArg('hierarchy_id') ?: 0;
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        // Delete items
        $response = $api->search('archivesspace_items', ['job_id' => $jobId]);
        $archivesspaceItems = $response->getContent();
        if ($archivesspaceItems) {
            foreach ($archivesspaceItems as $archivesspaceItem) {
                $archivesspaceResponse = $api->delete('archivesspace_items', $archivesspaceItem->id());
                $itemResponse = $api->delete('items', $archivesspaceItem->item()->id());
                $deletedCount++;
            }
        }
        
        // Delete item sets
        $response = $api->search('archivesspace_item_sets', ['job_id' => $jobId]);
        $archivesspaceItemSets = $response->getContent();
        if ($archivesspaceItemSets) {
            foreach ($archivesspaceItemSets as $archivesspaceItemSet) {
                $archivesspaceResponse = $api->delete('archivesspace_item_sets', $archivesspaceItemSet->id());
                $itemSetResponse = $api->delete('item_sets', $archivesspaceItemSet->itemSet()->id());
            }
        }

        // Delete hierarchy
        $hierarchyUpdater = $this->getServiceLocator()->get(\Hierarchy\Service\HierarchyUpdater\HierarchyUpdater::class);
        $hierarchyData = [
            'delete' => true,
            'id' => $hierarchyId,
        ];
        $hierarchyUpdater->updateHierarchy($hierarchyData);

        $deleteComment = $deletedCount ? $deletedCount . ' items deleted' : '';
        $archivesspaceImportJson = [
                            'o:job' => ['o:id' => $this->job->getId()],
                            'comment' => $deleteComment,
                            'added_count' => 0,
                            'updated_count' => 0,
                            'hierarchy_id' => 0,
                          ];
        $response = $api->create('archivesspace_imports', $archivesspaceImportJson);
        $jobArgs = $this->job->getArgs();
        $jobArgs['comment'] = $deleteComment;
        $jobArgs['hierarchy_id'] = 0;
        $this->job->setArgs($jobArgs);
    }
}
