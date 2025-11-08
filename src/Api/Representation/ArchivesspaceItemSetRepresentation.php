<?php
namespace ArchivesspaceConnector\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class ArchivesspaceItemSetRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'last_modified' => $this->getLastModified(),
            'aspace_api_url' => $this->getApiUrl(),
            'aspace_target_path' => $this->getTargetPath(),
            'o:item_set' => $this->getReference(),
            'o:job' => $this->getReference(),
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
