<?php
namespace ArchivesspaceConnector\Controller;

use Omeka\Stdlib\Message;
use ArchivesspaceConnector\Form\ImportForm;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Dom\Query;

class IndexController extends AbstractActionController
{
    protected $client;
    protected $apiUrl;
    protected $mainUri;
    protected $eadNs;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function indexAction()
    {
        $view = new ViewModel;
        $form = $this->getForm(ImportForm::class);
        $view->setVariable('form', $form);
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $job = $this->jobDispatcher()->dispatch('ArchivesspaceConnector\Job\Import', $data);
                // ArchivesspaceImport record is created in the job
                $message = new Message('Importing in Job ID %s', $job->getId()); // @translate
                $this->messenger()->addSuccess($message);
                $view->setVariable('job', $job);
                return $this->redirect()->toRoute('admin/archivesspace-connector/past-imports');
            } else {
                $this->messenger()->addError('There was an error during validation'); // @translate
            }
        }

        return $view;
    }

    public function pastImportsAction()
    {
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            if (isset($data['jobActions'])) {
                $undoJobIds = [];
                $rerunJobIds = [];
                foreach ($data['jobActions'] as $jobId => $action) {
                    if ($action == 'undo') {
                        $this->undoJob($jobId);
                        $undoJobIds[] = $jobId;
                    }
                    if ($action == 'rerun') {
                        $this->rerunJob($jobId);
                        $rerunJobIds[] = $jobId;
                    }
                }
                if (!empty($undoJobIds)) {
                    $message = new Message('Undo in progress on the following jobs: %s', // @translate
                        implode(', ', $undoJobIds));
                    $this->messenger()->addSuccess($message);
                }
                if (!empty($rerunJobIds)) {
                    $message = new Message('Rerun in progress on the following jobs: %s', // @translate
                        implode(', ', $rerunJobIds));
                    $this->messenger()->addSuccess($message);
                }
            } else {
                $this->messenger()->addError('Error: no jobs selected'); // @translate
            }
            return $this->redirect()->toRoute('admin/archivesspace-connector/past-imports');
        }
        $view = new ViewModel;
        $page = $this->params()->fromQuery('page', 1);
        $query = $this->params()->fromQuery() + [
            'page' => $page,
            'sort_by' => $this->params()->fromQuery('sort_by', 'job_id'),
            'sort_order' => $this->params()->fromQuery('sort_order', 'desc'),
        ];
        $response = $this->api()->search('archivesspace_imports', $query);
        $this->paginator($response->getTotalResults(), $page);
        $this->browse()->setDefaults('ac_past_imports');
        $view->setVariable('imports', $response->getContent());
        return $view;
    }

    protected function undoJob($jobId)
    {
        $response = $this->api()->search('archivesspace_imports', ['job_id' => $jobId]);
        $archivesspaceImport = $response->getContent()[0];
        // Get original import job args
        $deleteData = $archivesspaceImport->job()->args();
        $hierarchyId = $archivesspaceImport->hierarchyId() ?: null;
        $deleteData['hierarchy_id'] = $hierarchyId;
        $deleteData['previous_job'] = $jobId;
        $job = $this->jobDispatcher()->dispatch('ArchivesspaceConnector\Job\Undo', $deleteData);
        $response = $this->api()->update('archivesspace_imports',
                $archivesspaceImport->id(),
                [
                    'o:undo_job' => ['o:id' => $job->getId() ],
                ]
            );
    }

    protected function rerunJob($jobId)
    {
        $response = $this->api()->search('archivesspace_imports', ['job_id' => $jobId]);
        $archivesspaceImport = $response->getContent()[0];    
        // Get original import job args to run again
        $rerunData = $archivesspaceImport->job()->args();
        // Set previous job's created hierarchy (if any) to check against for updates
        $hierarchyId = $archivesspaceImport->hierarchyId() ?: null;
        $rerunData['hierarchy_id'] = $hierarchyId;
        $rerunData['rerun'] = true;
        $rerunData['previous_job'] = $jobId;
        $job = $this->jobDispatcher()->dispatch('ArchivesspaceConnector\Job\Import', $rerunData);
        $response = $this->api()->update('archivesspace_imports',
                $archivesspaceImport->id(),
                [
                    'o:rerun_job' => ['o:id' => $job->getId() ],
                ]
            );
    }

}
