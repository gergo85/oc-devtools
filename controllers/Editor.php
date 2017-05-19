<?php namespace Indikator\DevTools\Controllers;

use Url;
use Lang;
use Flash;
use Config;
use Request;
use Response;
use Exception;
use BackendMenu;
use Indikator\DevTools\Widgets\AssetList;
use Cms\Classes\Router;
use Cms\Classes\CmsCompoundObject;
use Cms\Classes\ComponentManager;
use Cms\Classes\ComponentPartial;
use Backend\Classes\Controller;
use Backend\Classes\WidgetManager;
use October\Rain\Router\Router as RainRouter;
use ApplicationException;

class Editor extends Controller
{
    use \Backend\Traits\InspectableContainer;

    public $requiredPermissions = ['indikator.devtools.editor'];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Indikator.DevTools', 'editor', 'assets');

        try {
            new AssetList($this, 'assetList');
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }
    }

    //
    // Pages
    //

    public function index()
    {
        $this->addJs('/modules/cms/assets/js/october.cmspage.js');
        $this->addJs('/modules/cms/assets/js/october.dragcomponents.js');
        $this->addJs('/modules/cms/assets/js/october.tokenexpander.js');
        $this->addCss('/modules/cms/assets/css/october.components.css');

        // Preload the code editor class as it could be needed
        // before it loads dynamically.
        $this->addJs('/modules/backend/formwidgets/codeeditor/assets/js/build-min.js');

        $this->bodyClass = 'compact-container';
        $this->pageTitle = 'indikator.devtools::lang.editor.menu_label';
        $this->pageTitleTemplate = '%s '.trans($this->pageTitle);

        if (Request::ajax() && Request::input('formWidgetAlias')) {
            $this->bindFormWidgetToController();
        }
    }

    public function index_onOpenTemplate()
    {
        $type = Request::input('type');
        $template = $this->loadTemplate($type, Request::input('path'));
        $widget = $this->makeTemplateFormWidget($type, $template);

        $this->vars['templatePath'] = Request::input('path');

        return [
            'tabTitle' => $this->getTabTitle($type, $template),
            'tab'      => $this->makePartial('form_page', [
                'form'          => $widget,
                'templateType'  => $type,
                'templateMtime' => $template->mtime
            ])
        ];
    }

    public function onSave()
    {
        $type = Request::input('templateType');
        $templatePath = trim(Request::input('templatePath'));
        $template = $templatePath ? $this->loadTemplate($type, $templatePath) : $this->createTemplate($type);
        $formWidget = $this->makeTemplateFormWidget($type, $template);

        $saveData = $formWidget->getSaveData();
        $postData = post();
        $templateData = [];

        $settings = array_get($saveData, 'settings', []) + Request::input('settings', []);
        $settings = $this->upgradeSettings($settings);

        if ($settings) {
            $templateData['settings'] = $settings;
        }

        $fields = ['markup', 'code', 'fileName', 'content'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $saveData)) {
                $templateData[$field] = $saveData[$field];
            }
            elseif (array_key_exists($field, $postData)) {
                $templateData[$field] = $postData[$field];
            }
        }

        if (!empty($templateData['markup']) && Config::get('cms.convertLineEndings', false) === true) {
            $templateData['markup'] = $this->convertLineEndings($templateData['markup']);
        }

        if (!empty($templateData['code']) && Config::get('cms.convertLineEndings', false) === true) {
            $templateData['code'] = $this->convertLineEndings($templateData['code']);
        }

        if (!Request::input('templateForceSave') && $template->mtime) {
            if (Request::input('templateMtime') != $template->mtime) {
                throw new ApplicationException('mtime-mismatch');
            }
        }

        $template->attributes = [];
        $template->fill($templateData);
        $template->save();

        /*
         * Extensibility
         */
        $this->fireSystemEvent('cms.template.save', [$template, $type]);

        Flash::success(Lang::get('cms::lang.template.saved'));

        $result = [
            'templatePath'  => $template->fileName,
            'templateMtime' => $template->mtime,
            'tabTitle'      => $this->getTabTitle($type, $template)
        ];

        return $result;
    }

    public function onOpenConcurrencyResolveForm()
    {
        return $this->makePartial('concurrency_resolve_form');
    }

    public function onCreateTemplate()
    {
        $type = Request::input('type');
        $template = $this->createTemplate($type);

        if ($type == 'asset') {
            $template->fileName = $this->widget->assetList->getCurrentRelativePath();
        }

        $widget = $this->makeTemplateFormWidget($type, $template);

        $this->vars['templatePath'] = '';

        return [
            'tabTitle' => $this->getTabTitle($type, $template),
            'tab'      => $this->makePartial('form_page', [
                'form'          => $widget,
                'templateType'  => $type,
                'templateMtime' => null
            ])
        ];
    }

    public function onDeleteTemplates()
    {
        $type = Request::input('type');
        $templates = Request::input('template');
        $error = null;
        $deleted = [];

        try {
            foreach ($templates as $path => $selected) {
                if ($selected) {
                    $this->loadTemplate($type, $path)->delete();
                    $deleted[] = $path;
                }
            }
        }
        catch (Exception $ex) {
            $error = $ex->getMessage();
        }

        /*
         * Extensibility
         */
        $this->fireSystemEvent('cms.template.delete', [$type]);

        return [
            'deleted' => $deleted,
            'error'   => $error,
            'theme'   => Request::input('theme')
        ];
    }

    public function onDelete()
    {
        $type = Request::input('templateType');

        $this->loadTemplate($type, trim(Request::input('templatePath')))->delete();

        /*
         * Extensibility
         */
        $this->fireSystemEvent('cms.template.delete', [$type]);
    }

    public function onExpandMarkupToken()
    {
        if (!$alias = post('tokenName')) {
            throw new ApplicationException(trans('cms::lang.component.no_records'));
        }

        // Can only expand components at this stage
        if ((!$type = post('tokenType')) && $type != 'component') {
            return;
        }

        if (!($names = (array) post('component_names')) || !($aliases = (array) post('component_aliases'))) {
            throw new ApplicationException(trans('cms::lang.component.not_found', ['name' => $alias]));
        }

        if (($index = array_get(array_flip($aliases), $alias, false)) === false) {
            throw new ApplicationException(trans('cms::lang.component.not_found', ['name' => $alias]));
        }

        if (!$componentName = array_get($names, $index)) {
            throw new ApplicationException(trans('cms::lang.component.not_found', ['name' => $alias]));
        }

        $manager = ComponentManager::instance();
        $componentObj = $manager->makeComponent($componentName);
        $partial = ComponentPartial::load($componentObj, 'default');
        $content = $partial->getContent();
        $content = str_replace('__SELF__', $alias, $content);

        return $content;
    }

    //
    // Methods for the internal use
    //

    protected function resolveTypeClassName($type)
    {
        $types = [
            'asset' => '\Indikator\DevTools\Classes\Asset'
        ];

        if (!array_key_exists($type, $types)) {
            throw new ApplicationException(trans('cms::lang.template.invalid_type'));
        }

        return $types[$type];
    }

    protected function loadTemplate($type, $path)
    {
        $class = $this->resolveTypeClassName($type);

        if (!($template = call_user_func([$class, 'load'], $this->theme, $path))) {
            throw new ApplicationException(trans('cms::lang.template.not_found'));
        }

        /*
        * Extensibility
         */
        $this->fireSystemEvent('cms.template.processSettingsAfterLoad', [$template]);

        return $template;
    }

    protected function createTemplate($type)
    {
        $class = $this->resolveTypeClassName($type);

        if (!($template = $class::inTheme($this->theme))) {
            throw new ApplicationException(trans('cms::lang.template.not_found'));
        }

        return $template;
    }

    protected function getTabTitle($type, $template)
    {
        if ($type == 'asset') {
            $result = in_array($type, ['asset', 'content']) ? $template->getFileName() : $template->getBaseFileName();

            if (!$result) {
                $result = trans('cms::lang.'.$type.'.new');
            }

            return $result;
        }

        return $template->getFileName();
    }

    protected function makeTemplateFormWidget($type, $template, $alias = null)
    {
        $formConfigs = [
            'asset' => '~/plugins/indikator/devtools/classes/asset/fields.yaml'
        ];

        if (!array_key_exists($type, $formConfigs)) {
            throw new ApplicationException(trans('cms::lang.template.not_found'));
        }

        $widgetConfig = $this->makeConfig($formConfigs[$type]);
        $widgetConfig->model = $template;
        $widgetConfig->alias = $alias ?: 'form'.studly_case($type).md5($template->getFileName()).uniqid();

        $widget = $this->makeWidget('Backend\Widgets\Form', $widgetConfig);

        return $widget;
    }

    protected function upgradeSettings($settings)
    {
        /*
         * Handle view bag
         */
        $viewBag = post('viewBag');
        if ($viewBag !== null) {
            $settings['viewBag'] = $viewBag;
        }

        /*
         * Extensibility
         */
        $dataHolder = (object) ['settings' => $settings];

        $this->fireSystemEvent('cms.template.processSettingsBeforeSave', [$dataHolder]);

        return $dataHolder->settings;
    }

    protected function bindFormWidgetToController()
    {
        $alias = Request::input('formWidgetAlias');
        $type = Request::input('templateType');
        $object = $this->loadTemplate($type, Request::input('templatePath'));

        $widget = $this->makeTemplateFormWidget($type, $object, $alias);
        $widget->bindToController();
    }

    /**
     * Replaces Windows style (/r/n) line endings with unix style (/n)
     * line endings.
     * @param string $markup The markup to convert to unix style endings
     * @return string
     */
    protected function convertLineEndings($markup)
    {
        $markup = str_replace("\r\n", "\n", $markup);
        $markup = str_replace("\r", "\n", $markup);

        return $markup;
    }
}
