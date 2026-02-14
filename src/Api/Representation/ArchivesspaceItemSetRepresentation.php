<?php
namespace ArchivesspaceConnector\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class ArchivesspaceItemSetRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'last_modified' => $this->lastModified(),
            'aspace_api_url' => $this->apiUrl(),
            'aspace_target_path' => $this->targetPath(),
            'o:item_set' => $this->itemSet(),
            'o:job' => $this->job(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o:ArchivesspaceItemSet';
    }

    public function lastModified()
    {
        return $this->resource->getlastModified();
    }

    public function apiUrl()
    {
        return $this->resource->getApiUrl();
    }

    public function targetPath()
    {
        return $this->resource->getTargetPath();
    }

    public function itemSet()
    {
        return $this->getAdapter('item_sets')
            ->getRepresentation($this->resource->getItemSet());
    }

    public function job()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getJob());
    }
}
