<?php
namespace ArchivesspaceConnector\Form;

use Omeka\Form\Element\ItemSetSelect;
use Omeka\Form\Element\SiteSelect;
use Omeka\Form\Element\ResourceSelect;
use Omeka\Settings\UserSettings;
use Omeka\Api\Manager as ApiManager;
use Omeka\Module\Manager as ModuleManager;
use Laminas\View\Helper\Url as UrlHelper;
use Laminas\Authentication\AuthenticationService;
use Laminas\Form\Form;

class ImportForm extends Form
{
    /**
     * @var UserSettings
     */
    protected $userSettings;

    /**
     * @var AuthenticationService
     */
    protected $AuthenticationService;

    /**
     * @var ApiManager
     */
    protected $apiManager;
    
    /**
     * @var ModuleManager
     */
    protected $moduleManager;
    
    /**
     * @var UrlHelper
     */
    protected $urlHelper;

    public function init()
    {
        $urlHelper = $this->getUrlHelper();
        
        $this->add([
            'name' => 'aspace_api_url',
            'type' => 'url',
            'options' => [
                'label' => 'ArchivesSpace API URL', // @translate
                'info' => 'The URL of the ArchiveSpace instance to import from. Example: https://sandbox.archivesspace.org/staff/api/', // @translate
            ],
            'attributes' => [
                'id' => 'aspace_api_url',
                'required' => true,
            ],
        ]);
        
        $this->add([
            'name' => 'aspace_target_path',
            'type' => 'text',
            'options' => [
                'label' => 'ArchivesSpace target path', // @translate
                'info' => 'The path to the ArchivesSpace collection you wish to import. Example: /repositories/2/resources/138', // @translate
            ],
            'attributes' => [
                'id' => 'aspace_target_path',
                'required' => true,
            ],
        ]);

        // Only allow for maintain_hierarchy if Hierarchy module installed.
        $hierarchyModule = $this->moduleManager->getModule('Hierarchy');
        if ($hierarchyModule && ModuleManager::STATE_ACTIVE === $hierarchyModule->getState()) {
            $disableHierarchy = false;
        } else {
            $disableHierarchy = true;
        }
        
        $this->add([
            'name' => 'maintain_hierarchy',
            'type' => 'checkbox',
            'options' => [
                'label' => 'Maintain collection hierarchy', // @translate
                'info' => '(Requires Hierarchy module) If checked, mimic ArchivesSpace collection structure by creating item sets for collection/series/subseries & organizing hierarchically with corresponding resources. Otherwise, import only lowest level Archival Object resources.', // @translate
            ],
            'attributes' => [
                'id' => 'maintain-hierarchy',
                'disabled' => $disableHierarchy,
                'value' => $disableHierarchy ? false : true,
            ],
        ]);
        
        $this->add([
            'name' => 'delete_missing_items',
            'type' => 'checkbox',
            'options' => [
                'label' => 'Delete missing items on update', // @translate
                'info' => 'Delete Omeka S items not found in ArchivesSpace on a rerun/update. If unchecked, items removed from ArchivesSpace collection will remain in Omeka S.', // @translate
            ],
            'attributes' => [
                'id' => 'delete-missing-items',
                'value' => true,
            ],
        ]);

        $this->add([
            'name' => 'comment',
            'type' => 'textarea',
            'options' => [
                'label' => 'Comment', // @translate
                'info' => 'A note about the purpose or source of this import', // @translate
            ],
            'attributes' => [
                'id' => 'comment',
            ],
        ]);

        $this->add([
            'name' => 'resource_template',
            'type' => ResourceSelect::class,
            'attributes' => [
                'id' => 'resource-template-select',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a template', // @translate
                'data-api-base-url' => $urlHelper('api-local/default', ['resource' => 'resource_templates']),
            ],
            'options' => [
                'label' => 'Resource template', // @translate
                'info' => 'Assign a resource template to all imported resources.', // @translate
                'empty_option' => 'Select a template',
                'resource_value_options' => [
                    'resource' => 'resource_templates',
                    'query' => [
                        'sort_by' => 'label',
                    ],
                    'option_text_callback' => function ($resourceTemplate) {
                        return $resourceTemplate->label();
                    },
                ],
            ],
        ]);
        
        // Merge assign_new_item sites and default user sites
        $defaultAddSiteRepresentations = $this->getApiManager()->search('sites', ['assign_new_items' => true])->getContent();
        foreach ($defaultAddSiteRepresentations as $defaultAddSiteRepresentation) {
            $defaultAddSites[] = $defaultAddSiteRepresentation->id();
        }
        $defaultAddSiteStrings = $defaultAddSites ?? [];

        $userId = $this->getAuthenticationService()->getIdentity()->getId();
        $userDefaultSites = $userId ? $this->getUserSettings()->get('default_item_sites', null, $userId) : [];
        $userDefaultSiteStrings = $userDefaultSites ?? [];

        $sites = array_merge($defaultAddSiteStrings, $userDefaultSiteStrings);

        $this->add([
            'name' => 'itemSites',
            'type' => SiteSelect::class,
            'attributes' => [
                'value' => $sites,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select site(s)', // @translate
                'multiple' => true,
                'id' => 'item-sites',
            ],
            'options' => [
                'label' => 'Sites', // @translate
                'info' => 'Optional. Import new items and item sets into site(s).', // @translate
                'empty_option' => '',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'resource_template',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'itemSites',
            'required' => false,
        ]);
    }

    /**
     * @param UserSettings $userSettings
     */
    public function setUserSettings(UserSettings $userSettings)
    {
        $this->userSettings = $userSettings;
    }

    /**
     * @return UserSettings
     */
    public function getUserSettings()
    {
        return $this->userSettings;
    }

    /**
     * @param AuthenticationService $AuthenticationService
     */
    public function setAuthenticationService(AuthenticationService $AuthenticationService)
    {
        $this->AuthenticationService = $AuthenticationService;
    }

    /**
     * @return AuthenticationService
     */
    public function getAuthenticationService()
    {
        return $this->AuthenticationService;
    }
    
    /**
     * @param Url $urlHelper
     */
    public function setUrlHelper(UrlHelper $urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return Url
     */
    public function getUrlHelper()
    {
        return $this->urlHelper;
    }

    /**
     * @param ApiManager $apiManager
     */
    public function setApiManager(ApiManager $apiManager)
    {
        $this->apiManager = $apiManager;
    }

    /**
     * @return ApiManager
     */
    public function getApiManager()
    {
        return $this->apiManager;
    }
    
    public function setModuleManager(ModuleManager $moduleManager)
    {
        $this->moduleManager = $moduleManager;
    }
}
