<?php
namespace ArchivesspaceConnector\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ArchivesspaceItemAdapter extends AbstractEntityAdapter
{
    public function getEntityClass()
    {
        return 'ArchivesspaceConnector\Entity\ArchivesspaceItem';
    }

    public function getResourceName()
    {
        return 'archivesspace_items';
    }

    public function getRepresentationClass()
    {
        return 'ArchivesspaceConnector\Api\Representation\ArchivesspaceItemRepresentation';
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['aspace_api_url'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.aspaceApiUrl',
                $this->createNamedParameter($qb, $query['aspace_api_url']))
            );
        }

        if (isset($query['aspace_target_path'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.aspaceTargetPath',
                $this->createNamedParameter($qb, $query['aspace_target_path']))
            );
        }

        if (isset($query['job_id'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.job',
                $this->createNamedParameter($qb, $query['job_id']))
            );
        }

        if (isset($query['item_id'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.item',
                $this->createNamedParameter($qb, $query['item_id']))
            );
        }
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();
        if (isset($data['aspace_api_url'])) {
            $entity->setApiUrl($data['aspace_api_url']);
        }
        if (isset($data['aspace_target_path'])) {
            $entity->setTargetPath($data['aspace_target_path']);
        }
        if (isset($data['o:job']['o:id'])) {
            $job = $this->getAdapter('jobs')->findEntity($data['o:job']['o:id']);
            $entity->setJob($job);
        }
        if (isset($data['o:item']['o:id'])) {
            $item = $this->getAdapter('items')->findEntity($data['o:item']['o:id']);
            $entity->setItem($item);
        }
        if (isset($data['last_modified'])) {
            $entity->setLastModified($data['last_modified']);
        }
    }
}
