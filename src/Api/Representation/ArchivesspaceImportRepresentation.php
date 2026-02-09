<?php
namespace ArchivesspaceConnector\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class ArchivesspaceImportRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        if ($this->undoJob()) {
            $undo_job = $this->undoJob()->getReference();
        }
        if ($this->rerunJob()) {
            $rerun_job = $this->rerunJob()->getReference();
        }
        return [
            'added_item_count' => $this->addedItems(),
            'updated_item_count' => $this->updatedItems(),
            'added_itemset_count' => $this->addedItemSets(),
            'updated_itemset_count' => $this->updatedItemSets(),
            'comment' => $this->comment(),
            'hierarchy_id' => $this->hierarchyId(),
            'o:job' => $this->getReference(),
            'o:undo_job' => $undo_job,
            'o:rerun_job' => $rerun_job,
        ];
    }

    public function getJsonLdType()
    {
        return 'o:ArchivesspaceImport';
    }

    public function job()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getJob());
    }

    public function undoJob()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getUndoJob());
    }

    public function rerunJob()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getRerunJob());
    }

    public function comment()
    {
        return $this->resource->getComment();
    }

    public function addedItems()
    {
        return $this->resource->getAddedItems();
    }

    public function updatedItems()
    {
        return $this->resource->getUpdatedItems();
    }

    public function addedItemSets()
    {
        return $this->resource->getAddedItemSets();
    }

    public function updatedItemSets()
    {
        return $this->resource->getUpdatedItemSets();
    }

    public function hierarchyId()
    {
        return $this->resource->getHierarchyId();
    }
}
