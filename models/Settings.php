<?php namespace Indikator\DevTools\Models;

use Model;
use Db;

class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'indikator_devtools_settings';

    public $settingsFields = 'fields.yaml';

    public $selectList = [0 => 'indikator.devtools::lang.form.select_none'];

    public function getHelpAdmingroupOptions()
    {
        $result = $this->selectList;
        $sql = Db::table('backend_user_groups')->orderBy('name', 'asc')->get()->all();

        foreach ($sql as $item) {
            $result[$item->id] = $item->name.' ('.Db::table('backend_users_groups')->where('user_group_id', $item->id)->count().')';
        }

        return $result;
    }

    public function getHelpAdminidOptions()
    {
        $result = $this->selectList;
        $sql = Db::table('backend_users')->orderBy('login', 'asc')->get()->all();

        foreach ($sql as $item) {
            $result[$item->id] = $item->login.' ('.$item->email.')';
        }

        return $result;
    }

    public function getWysiwygAdmingroupOptions()
    {
        $result = $this->selectList;
        $sql = Db::table('backend_user_groups')->orderBy('name', 'asc')->get()->all();

        foreach ($sql as $item) {
            $result[$item->id] = $item->name.' ('.Db::table('backend_users_groups')->where('user_group_id', $item->id)->count().')';
        }

        return $result;
    }

    public function getWysiwygAdminidOptions()
    {
        $result = $this->selectList;
        $sql = Db::table('backend_users')->orderBy('login', 'asc')->get()->all();

        foreach ($sql as $item) {
            $result[$item->id] = $item->login.' ('.$item->email.')';
        }

        return $result;
    }
}
