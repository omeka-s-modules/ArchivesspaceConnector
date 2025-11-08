<?php
namespace ArchivesspaceConnector\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class ArchivesspaceItemRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'last_modified' => $this->getLastModified(),
            'aspace_api_url' => $this->getApiUrl(),
            'aspace_target_path' => $this->getTargetPath(),
            'o:item' => $this->getReference(),
            'o:job' => $this->getReference(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o:ArchivesspaceItem';
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

    public function item()
    {
        return $this->getAdapter('items')
            ->getRepresentation($this->resource->getItem());
    }

    public function job()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getJob());
    }
}
