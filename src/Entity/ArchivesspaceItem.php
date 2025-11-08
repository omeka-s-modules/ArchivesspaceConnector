<?php
namespace ArchivesspaceConnector\Entity;

use DateTime;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Job;
use Omeka\Entity\Item;

/**
 * @Entity
 */
class ArchivesspaceItem extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @OneToOne(targetEntity="Omeka\Entity\Item")
     * @JoinColumn(nullable=false, onDelete="CASCADE")
     * @var int
     */
    protected $item;

    /**
     * @ManyToOne(targetEntity="Omeka\Entity\Job")
     * @JoinColumn(nullable=false)
     */
    protected $job;

    /**
     * @Column(type="string")
     * @var string
     */
    protected $aspaceApiUrl;

    /**
     * @Column(type="string")
     * @var string
     */
    protected $aspaceTargetPath;

    /**
     * @Column(type="datetime")
     */
    protected $lastModified;

    public function getId()
    {
        return $this->id;
    }

    public function getItem()
    {
        return $this->item;
    }

    public function setItem(Item $item)
    {
        $this->item = $item;
    }

    public function setJob(Job $job)
    {
        $this->job = $job;
    }

    public function getJob()
    {
        return $this->job;
    }

    public function setApiUrl($aspaceApiUrl)
    {
        $this->aspaceApiUrl = $aspaceApiUrl;
    }

    public function getApiUrl()
    {
        return $this->aspaceApiUrl;
    }

    public function setTargetPath($aspaceTargetPath)
    {
        $this->aspaceTargetPath = $aspaceTargetPath;
    }

    public function getTargetPath()
    {
        return $this->aspaceTargetPath;
    }

    public function setLastModified(DateTime $lastModified)
    {
        $this->lastModified = $lastModified;
    }

    public function getLastModified()
    {
        return $this->lastModified;
    }
}
