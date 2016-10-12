<?php namespace Indikator\DevTools;

use System\Classes\PluginBase;
use System\Classes\SettingsManager;
use Event;
use Backend;
use BackendMenu;
use BackendAuth;
use Indikator\DevTools\Models\Settings as Tools;
use DB;

class Plugin extends PluginBase
{
    public $elevated = true;

    public function pluginDetails()
    {
        return [
            'name'        => 'indikator.devtools::lang.plugin.name',
            'description' => 'indikator.devtools::lang.plugin.description',
            'author'      => 'indikator.devtools::lang.plugin.author',
            'icon'        => 'icon-wrench',
            'homepage'    => 'https://github.com/gergo85/oc-devtools'
        ];
    }

    public function registerSettings()
    {
        return [
            'devtool' => [
                'label'       => 'indikator.devtools::lang.help.menu_label',
                'description' => 'indikator.devtools::lang.help.menu_description',
                'icon'        => 'icon-wrench',
                'class'       => 'Indikator\DevTools\Models\Settings',
                'category'    => SettingsManager::CATEGORY_SYSTEM,
                'permissions' => ['indikator.devtools.settings']
            ]
        ];
    }

    public function registerFormWidgets()
    {
        return [
            'Indikator\DevTools\FormWidgets\Help' => [
                'label' => 'Help',
                'code'  => 'help'
            ]
        ];
    }

    public function registerPermissions()
    {
        return [
            'indikator.devtools.editor' => [
                'tab'   => 'indikator.devtools::lang.plugin.name',
                'label' => 'indikator.devtools::lang.editor.permission'
            ],
            'indikator.devtools.settings' => [
                'tab'   => 'indikator.devtools::lang.plugin.name',
                'label' => 'indikator.devtools::lang.help.permission'
            ]
        ];
    }

    public function boot()
    {
        BackendMenu::registerCallback(function ($manager) {
            $manager->registerMenuItems('Indikator.DevTools', [
                'editor' => [
                    'label'       => 'indikator.devtools::lang.editor.menu_label',
                    'url'         => Backend::url('indikator/devtools/editor'),
                    'icon'        => 'icon-file-code-o',
                    'permissions' => ['indikator.devtools.editor'],
                    'order'       => 500,

                    'sideMenu' => [
                        'assets' => [
                            'label'        => 'indikator.devtools::lang.editor.plugins',
                            'icon'         => 'icon-cubes',
                            'url'          => 'javascript:;',
                            'attributes'   => ['data-menu-item' => 'assets'],
                            'counterLabel' => 'cms::lang.asset.unsaved_label'
                        ]
                    ]
                ]
            ]);
        });

        Event::listen('backend.form.extendFields', function($form)
        {
            // Help docs
            if ($this->tools_enabled('help') && (get_class($form->config->model) == 'Cms\Classes\Page' || get_class($form->config->model) == 'Cms\Classes\Partial' || get_class($form->config->model) == 'Cms\Classes\Layout') || get_class($form->config->model) == 'Indikator\DevTools\Classes\Asset') {
                if (get_class($form->config->model) == 'Indikator\DevTools\Classes\Asset') {
                    $content = 'php';
                }
                else {
                    $content = 'cms';
                }

                $form->addSecondaryTabFields([
                    'help' => [
                        'label'   => '',
                        'tab'     => 'indikator.devtools::lang.help.tab',
                        'type'    => 'help',
                        'content' => $content
                    ]
                ]);

                return;
            }

            // Wysiwyg editor
            if ($this->tools_enabled('wysiwyg') && get_class($form->config->model) == 'Cms\Classes\Content') {
                foreach ($form->getFields() as $field) {
                    if (!empty($field->config['type']) && $field->config['type'] == 'codeeditor') {
                        $field->config['type'] = $field->config['widget'] = 'richeditor';
                    }
                }
            }
        });
    }

    public function tools_enabled($name)
    {
        // Security check
        if ($name != 'help' && $name != 'wysiwyg') {
            return false;
        }

        // Is enabled
        if (!Tools::get($name.'_enabled', false)) {
            return false;
        }

        // My account
        $admin = BackendAuth::getUser();

        // Is superuser
        if (Tools::get($name.'_superuser', false) && $admin->is_superuser == 1) {
            return true;
        }

        // Is admin group
        if (Tools::get($name.'_admingroup', false) > 0 && DB::table('backend_users_groups')->where('user_id', $admin->id)->where('user_group_id', Tools::get($name.'_admingroup', false))->count() == 1) {
            return true;
        }

        // Is current user
        if (Tools::get($name.'_adminid', false) > 0 && $admin->id == Tools::get($name.'_adminid', false)) {
            return true;
        }

        // Finish
        return false;
    }
}
