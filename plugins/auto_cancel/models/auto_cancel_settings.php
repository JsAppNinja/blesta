<?php
class AutoCancelSettings extends AppModel
{
    public function __construct()
    {
        parent::__construct();

        if (!isset($this->SettingsCollection)) {
            Loader::loadComponents($this, array('SettingsCollection'));
        }

        Language::loadLang(
            'auto_cancel_settings',
            null,
            PLUGINDIR . 'auto_cancel' . DS . 'language' . DS
        );
    }

    /**
     * Fetches settings
     *
     * @param int $company_id
     * @return array
     */
    public function getSettings($company_id)
    {
        $supported = $this->supportedSettings();
        $company_settings = $this->SettingsCollection
            ->fetchSettings(null, $company_id);
        $settings = array();
        foreach ($company_settings as $setting => $value) {
            if (($index = array_search($setting, $supported)) !== false) {
                $settings[$index] = $value;
            }
        }
        return $settings;
    }

    /**
     * Set settings
     *
     * @param int $company_id
     * @param array $settings Key/value pairs
     */
    public function setSettings($company_id, array $settings)
    {
        if (!isset($this->Companies)) {
            Loader::loadModels($this, array('Companies'));
        }

        $valid_settings = array();
        foreach ($this->supportedSettings() as $key => $name) {
            if (array_key_exists($key, $settings)) {
                $valid_settings[$name] = $settings[$key];
            }
        }

        $this->Input->setRules($this->getRules($valid_settings));
        if ($this->Input->validates($valid_settings)) {
            $this->Companies->setSettings($company_id, $valid_settings);
        }
    }

    /**
     * Fetch supported settings
     *
     * @return array
     */
    public function supportedSettings()
    {
        return array(
            'schedule_days' => 'auto_cancel.schedule_days',
            'cancel_days' => 'auto_cancel.cancel_days'
        );
    }

    /**
     * Input validate rules
     *
     * @param array $vars
     * @return array
     */
    private function getRules($vars)
    {
        return array(
            'auto_cancel.schedule_days' => array(
                'valid' => array(
                    'rule' => array(array($this, 'isValidDay')),
                    'message' => $this->_('AutoCancelSettings.!error.schedule_days.valid')
                )
            ),
            'auto_cancel.cancel_days' => array(
                'valid' => array(
                    'rule' => array(array($this, 'isValidDay')),
                    'message' => $this->_('AutoCancelSettings.!error.cancel_days.valid')
                ),
                'greater' => array(
                    'rule' => array('compares', '>=', $vars['auto_cancel.schedule_days']),
                    'message' => $this->_('AutoCancelSettings.!error.cancel_days.greater')
                )
            ),
        );
    }

    /**
     * Validate the day given
     *
     * @param string $day
     * @return boolean
     */
    public function isValidDay($day)
    {
        return $day === '' || ($day >= 0 && $day <= 60);
    }
}
