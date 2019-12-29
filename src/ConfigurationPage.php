<?php
/**
 * Copyright (c) 2019 Signal Wow
 * ███████╗██╗ ██████╗ ███╗   ██╗ █████╗ ██╗         ██╗    ██╗ ██████╗ ██╗    ██╗
 * ██╔════╝██║██╔════╝ ████╗  ██║██╔══██╗██║         ██║    ██║██╔═══██╗██║    ██║
 * ███████╗██║██║  ███╗██╔██╗ ██║███████║██║         ██║ █╗ ██║██║   ██║██║ █╗ ██║
 * ╚════██║██║██║   ██║██║╚██╗██║██╔══██║██║         ██║███╗██║██║   ██║██║███╗██║
 * ███████║██║╚██████╔╝██║ ╚████║██║  ██║███████╗    ╚███╔███╔╝╚██████╔╝╚███╔███╔╝
 * ╚══════╝╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚═╝  ╚═╝╚══════╝     ╚══╝╚══╝  ╚═════╝  ╚══╝╚══╝
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to a commercial license from Signal Wow.
 * Use, copy, modification or distribution of this source file without written
 * license agreement from Signal Wow is strictly forbidden.
 * In order to obtain a license, please contact us: contact@signalwow.fr.
 *
 * @author Signal Wow <contact@signalwow.fr>
 * @copyright Copyright (c) Signal Wow
 * @license Commercial license
 */

namespace SignalWow\ConfigurationPage;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Module;
use PrestaShop\PrestaShop\Adapter\Entity\Category;
use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use PrestaShop\PrestaShop\Adapter\Entity\Context;
use PrestaShop\PrestaShop\Adapter\Entity\HelperForm;
use PrestaShop\PrestaShop\Adapter\Entity\HelperTreeCategories;
use PrestaShop\PrestaShop\Adapter\Entity\Language;
use PrestaShop\PrestaShop\Adapter\Entity\Tools;
use SignalWow\KickStart\forms\DefaultConfigForm;
use SignalWow\KickStart\forms\DemoConfigForm;

class ConfigurationPage
{
    /** @var ConfigurationPage */
    private static $instance;
    /** @var Module */
    private $module;
    /** @var array */
    private $configFormDefinitions;
    /** @var string */
    private $prefix = '';
    /** @var string */
    private $formName;
    /** @var string */
    private $table = 'module';
    /** @var string */
    private $identifier = 'id_module';
    /** @var array */
    private $defaultConfigurations = [];

    /**
     * ConfigurationPage constructor.
     */
    private function __construct()
    {
    }

    /**
     * @param array $configValues
     * @return $this
     */
    public function addConfigurationValues($configValues)
    {
        $configValues                  = (array)$configValues;
        $this->defaultConfigurations[] = $configValues;

        return $this;
    }

    /**
     * @param array $configFormDefinition
     * @return ConfigurationPage
     */
    public function addForm($configFormDefinition)
    {
        $this->configFormDefinitions[] = (array)$configFormDefinition;

        return $this;
    }

    /**
     * Remove settings in database
     * @return bool
     */
    public function deleteConfigurations()
    {
        $result = true;

        foreach ($this->configFormDefinitions as $formKey => $configFormDefinition) {

            foreach ((array)$this->getConfigFormValues($formKey) as $configKey => $configValue) {

                if (is_array($configValue)) {

                    foreach ($configValue as $lang => $param) {

                        $result = $result && Configuration::deleteByName($configKey . '_' . $lang);

                        continue;
                    }
                }

                $result = $result && Configuration::deleteByName($configKey);
            }
        }

        return $result;
    }

    /**
     * Set values for the inputs.
     */
    private function getConfigFormValues($formKey)
    {
        $formKey          = (int)$formKey;
        $configForms      = $this->getConfigForms();
        $languages        = Language::getLanguages(false);
        $configFormValues = [];

        foreach ($configForms[$formKey]['form']['input'] as $inputConfig) {

            if (array_key_exists('lang', $inputConfig)) {

                foreach ($languages as $language) {

                    $configFormValues[$inputConfig['name']][$language['id_lang']] = Tools::getValue(
                        $inputConfig['name'] . '_' . $language['id_lang'],
                        Configuration::get($inputConfig['name'] . '_' . $language['id_lang'])
                    );
                }

                continue;
            }

            $configFormValues[$inputConfig['name']] = Tools::getValue(
                $inputConfig['name'],
                Configuration::get($inputConfig['name'])
            );
        }

        return $configFormValues;
    }

    /**
     * @return array
     */
    private function getConfigForms()
    {
        $configForms = [];

        foreach ($this->configFormDefinitions as $formKey => $configFormDefinition) {
            $configForms[] = $this->getConfigForm((int)$formKey);
        }

        return $configForms;
    }

    /**
     * @param int $formKey
     * @return array
     */
    private function getConfigForm($formKey)
    {
//        if ($this->configFormDefinitions === null) {
//            $this->configFormDefinitions = $this->getDefaultConfigForm();
//        }

        $formKey = (int)$formKey;

        return $this->prepareConfigForm($this->configFormDefinitions[$formKey]);
    }

    /**
     * @param array $configForm
     * @return array
     */
    private function prepareConfigForm(array $configForm)
    {
        foreach ($configForm as $key => $config) {

            if (is_array($config)) {
                $configForm[$key] = $this->prepareConfigForm($config);
                if (array_key_exists('type', $config) && $config['type'] === 'categories') {
                    $configForm[$key] = $this->fixMultipleCategoryTrees($config);
                }
            }

            if ($key === 'name') {
                $configForm[$key] = $this->prefix . $config;
            }
        }

        return $configForm;
    }

    /**
     * @param array $categoryTreeParams
     * @return array
     */
    private function fixMultipleCategoryTrees(array $categoryTreeParams)
    {
        $root = Category::getRootCategory();

        $tree = new HelperTreeCategories(Tools::strtolower($categoryTreeParams['name']));

        $tree
            ->setUseCheckBox(false)
            ->setAttribute('is_category_filter', $root->id)
            ->setRootCategory($root->id)
            ->setSelectedCategories([(int)Configuration::get($this->prefix . $categoryTreeParams['name'])])
            ->setInputName($this->prefix . $categoryTreeParams['name']);

        $categoryTreeHTML = $tree->render();

        $output = [
            'type'          => 'categories_select',
            'label'         => $categoryTreeParams['label'],
            'name'          => $this->prefix . $categoryTreeParams['name'],
            'category_tree' => $categoryTreeHTML,
        ];

        if (array_key_exists('desc', $categoryTreeParams)) {
            $output['desc'] = $categoryTreeParams['desc'];
        }

        return $output;
    }

    /**
     * @return ConfigurationPage
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new ConfigurationPage();
        }

        return self::$instance;
    }

    /**
     * @param array $defaultConfigurationValues
     * @param Module $module
     * @return string
     */
    public function initDefaultConfigurationValues($defaultConfigurationValues, Module $module)
    {
        $preparedDefaultConfigurations = [];
        $this->module                  = $module;

        foreach ((array)$defaultConfigurationValues as $key => $defaultConfiguration) {
            $preparedDefaultConfigurations[$this->prefix . $key] = $defaultConfiguration;
        }

        return $this->postProcess($preparedDefaultConfigurations);
    }

    /**
     * Save form data.
     * @param array $formValues
     * @return string
     */
    private function postProcess($formValues)
    {
        foreach ((array)$formValues as $key => $value) {

            Configuration::updateValue($key, Tools::getValue($key, (string)$value));
        }

        return $this->module->displayConfirmation($this->module->l('Settings updated'));
    }

    /**
     * Load the configuration form
     * @param Module $module
     * @return string
     */
    public function renderConfigPage(Module $module)
    {
        $this->module   = $module;
        $successMessage = '';

//        if ($this->formName === null) {
//            $this->setFormName($this->prefix . $this->module->name);
//        }

        /**
         * If values have been submitted in the form, process.
         */
//        if (((bool)Tools::isSubmit($this->formName)) === true) {
//            $successMessage = $this->postProcess($this->getConfigFormValues());
//        }

//        foreach ($this->configFormDefinitions as $formKey => $configFormDefinition) {
//            dd(Tools::getAllValues());

        if (((bool)Tools::isSubmit($this->prefix . $this->module->name . '_form')) === true) {

            $successMessage = $this->postProcess($this->getConfigFormsValues());
        }
//        }

//        $output = $this->module->fetch(
//            $this->module->getLocalPath() . 'views' .
//            DIRECTORY_SEPARATOR . 'admin' .
//            DIRECTORY_SEPARATOR . 'templates' .
//            DIRECTORY_SEPARATOR . $this->prefix . 'configure.tpl'
//        );

//        return $successMessage . $this->renderForm() . $output;
        return $successMessage . $this->renderForms();
    }

    /**
     * @return string
     */
    private function renderForms()
    {
        /// TODO ///////////////////////////////////////////////////////////////////////////////////////////////////////

//        $output = '';
//
//        foreach ($this->configFormDefinitions as $formKey => $configFormDefinition) {
//            $output .= $this->renderForm((int)$formKey);
//        }
//
//        return $output;

        /// TODO ///////////////////////////////////////////////////////////////////////////////////////////////////////

        $helper  = new HelperForm();
        $context = Context::getContext();

        $helper->show_toolbar             = false;
        $helper->table                    = $this->table;
        $helper->module                   = $this->module;
        $helper->default_form_language    = $context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier               = $this->identifier;
        $helper->submit_action            = $this->prefix . $this->module->name . '_form';
        $helper->token                    = Tools::getAdminTokenLite('AdminModules');

        $helper->currentIndex =
            $context->link->getAdminLink('AdminModules', false) .
            '&configure=' . $this->module->name .
            '&tab_module=' . $this->module->tab .
            '&module_name=' . $this->module->name;

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormsValues(),
            'languages'    => $context->controller->getLanguages(),
            'id_language'  => $context->language->id,
        ];

        return $helper->generateForm($this->getConfigForms());
    }

    /**
     * @return array
     */
    private function getConfigFormsValues()
    {
        $configFormsValues = [];

        foreach ($this->configFormDefinitions as $formKey => $configFormDefinition) {

            foreach ($configFormDefinition['form']['input'] as $configKey => $configValue) {

                $configFormsValues[$this->prefix . $configValue['name']] = Configuration::get($this->prefix . $configValue['name']);
            }
        }

        return $configFormsValues;
    }

    /**
     * Creating form for the configuration page
     * @param $formKey
     * @return string
     */
    private function renderForm($formKey)
    {
        $formKey = (int)$formKey;
        $helper  = new HelperForm();
        $context = Context::getContext();

        $helper->show_toolbar             = false;
        $helper->table                    = $this->table;
        $helper->module                   = $this->module;
        $helper->default_form_language    = $context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier               = $this->identifier;
        $helper->submit_action            = key($this->configFormDefinitions[$formKey]) . '_' . $formKey;
        $helper->token                    = Tools::getAdminTokenLite('AdminModules');

        $helper->currentIndex =
            $context->link->getAdminLink('AdminModules', false) .
            '&configure=' . $this->module->name .
            '&tab_module=' . $this->module->tab .
            '&module_name=' . $this->module->name;

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues($formKey),
            'languages'    => $context->controller->getLanguages(),
            'id_language'  => $context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm($formKey)]);
    }

    /**
     * @param array $configFormDefinition
     * @return $this
     */
    public function setConfigFormDefinition($configFormDefinition)
    {
        $this->configFormDefinitions = $configFormDefinition;
        return $this;
    }

    /**
     * @param $defaultConfigurations
     * @return $this
     */
    public function setDefaultConfigurationValues($defaultConfigurations)
    {
        $this->defaultConfigurations = $defaultConfigurations;

        return $this;
    }

    /**
     * @return $this
     */
    public function setDemoConfigFormDefinition()
    {
        $this->configFormDefinitions = $this->getDemoConfigForm();

        return $this;
    }

    /**
     * @return array
     */
    private function getDemoConfigForm()
    {
        $defaultConfigForm = new DemoConfigForm();
        return $defaultConfigForm->getDemoConfigForm($this->module);
    }

    /**
     * @param string $formName
     * @return $this
     */
    public function setFormName($formName)
    {
        $this->formName = $formName;
        return $this;
    }

    /**
     * @param string $identifier
     * @return $this
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
        return $this;
    }

    /**
     * @param Module $module
     * @return $this
     */
    public function setModule(Module $module)
    {
        $this->module = $module;
        return $this;
    }

    /**
     * @param string $prefix
     * @return $this
     */
    public function setPrefix($prefix)
    {
        $this->prefix = (string)$prefix;
        return $this;
    }

    /**
     * @param string $table
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @return array
     */
    private function getDefaultConfigForm()
    {
        $defaultConfigForm = new DefaultConfigForm();

        return $defaultConfigForm->getDefaultConfigForm($this->module);
    }
}
