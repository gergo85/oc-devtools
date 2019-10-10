<?php namespace Indikator\DevTools\FormWidgets;

use Backend\Classes\FormField;
use Backend\Classes\FormWidgetBase;

class Help extends FormWidgetBase
{
    protected $defaultAlias = 'help';

    public $content = 'cms';

    public function init()
    {
        $this->fillFromConfig([
            'content'
        ]);
    }

    public function render()
    {
        $this->prepareVars();

        return $this->makePartial('help');
    }

    protected function prepareVars()
    {
        $this->vars['content'] = $this->content;
    }
}
