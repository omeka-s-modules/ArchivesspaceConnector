<?php
namespace ArchivesspaceConnector\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Job;

/**
 * @Entity
 */
class ArchivesspaceImport extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @OneToOne(targetEntity="Omeka\Entity\Job")
     * @JoinColumn(nullable=false)
     */
    protected $job;

    /**
     * @Column(type="integer")
     */
    protected $addedItems;

    /**
     * @Column(type="integer")
     */
    protected $updatedItems;

    /**
     * @Column(type="integer")
     */
    protected $addedItemSets;

    /**
     * @Column(type="integer")
     */
    protected $updatedItemSets;

    /**
     * @OneToOne(targetEntity="Omeka\Entity\Job")
     * @JoinColumn(nullable=true)
     */
    protected $undoJob;

    /**
     * @OneToOne(targetEntity="Omeka\Entity\Job")
     * @JoinColumn(nullable=true)
     */
    protected $rerunJob;

    /**
     * @Column(type="text", nullable=true)
     */
    protected $comment;
    
    /**
     * @Column(type="integer")
     */
    protected $hierarchyId;

    public function getId()
    {
        return $this->id;
    }

    public function setJob(Job $job)
    {
        $this->job = $job;
    }

    public function getJob()
    {
        return $this->job;
    }

    public function setUndoJob(Job $job)
    {
        $this->undoJob = $job;
    }

    public function getUndoJob()
    {
        return $this->undoJob;
    }

    public function setRerunJob(Job $job)
    {
        $this->rerunJob = $job;
    }

    public function getRerunJob()
    {
        return $this->rerunJob;
    }

    public function setAddedItems($count)
    {
        $this->addedItems = $count;
    }

    public function getAddedItems()
    {
        return $this->addedItems;
    }

    public function setUpdatedItems($count)
    {
        $this->updatedItems = $count;
    }

    public function getUpdatedItems()
    {
        return $this->updatedItems;
    }

    public function setAddedItemSets($count)
    {
        $this->addedItemSets = $count;
    }

    public function getAddedItemSets()
    {
        return $this->addedItemSets;
    }

    public function setUpdatedItemSets($count)
    {
        $this->updatedItemSets = $count;
    }

    public function getUpdatedItemSets()
    {
        return $this->updatedItemSets;
    }

    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function setHierarchyId($hierarchyId)
    {
        $this->hierarchyId = $hierarchyId;
    }

    public function getHierarchyId()
    {
        return $this->hierarchyId;
    }
}
