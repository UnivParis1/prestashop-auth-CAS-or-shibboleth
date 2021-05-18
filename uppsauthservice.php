<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once (dirname(__FILE__) . '/classes/AuthService.php');
require_once (dirname(__FILE__) . '/classes/AuthServiceShibboleth.php');
// require_once (dirname(__FILE__) . '/classes/AuthServiceCAS.php');
// require_once (dirname(__FILE__) . '/classes/AuthServiceLDAP.php');
require_once (dirname(__FILE__) . '/classes/AuthServiceLog.php');

class UppsAuthService extends Module
{
    protected $config_form = false;
    protected $ssoData = false;

    public function __construct()
    {
        $this->name = 'uppsauthservice';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'NewQuest';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Université Paris 1 Panthéon-Sorbonne - Authentication service', [], 'Modules.UppsAuthService.Admin');
        $this->description = $this->trans('Authentication service via Shibboleth', [], 'Modules.UppsAuthService.Admin');
        $this->confirmUninstall = $this->trans('Are you sure to uninstall this module ?', [], 'Modules.UppsAuthService.Admin');
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];

        $this->getSSOData();
    }

    public function install()
    {
        include(dirname(__FILE__) . '/sql/install.php');
        return parent::install()
            && $this->registerHook('displayCustomerLoginFormAfter')
            && $this->registerHook('actionFrontControllerAfterInit')
            && $this->registerHook('actionCustomerLogoutAfter')
            && $this->registerHook('actionObjectCustomerDeleteAfter');
    }

    public function uninstall()
    {
        include(dirname(__FILE__) . '/sql/uninstall.php');
        return parent::uninstall();
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitUpdate')) {
            Configuration::updateValue('UPPSA_AUTHENTICATION_TYPE', Tools::getValue('authentificationType'));
            Configuration::updateValue('UPPSA_SHIB_LOGIN', Tools::getValue('shibLogin'));
            Configuration::updateValue('UPPSA_SHIB_LOGOUT', Tools::getValue('shibLogout'));
            Configuration::updateValue('UPPSA_SHIB_COUNTRY_CODE', Tools::getValue('shibCountryCode'));

            $language_ids = Language::getIDs(false);
            $datas = [];
            foreach ($language_ids as $id_lang) {
                $datas['boxTitle'][$id_lang] = Tools::getValue('boxTitle_' . $id_lang, Configuration::get('UPPSA_BOX_TITLE', $id_lang)) ;
                $datas['boxText'][$id_lang] = Tools::getValue('boxText_' . $id_lang, Configuration::get('UPPSA_BOX_TEXT', $id_lang)) ;
                $datas['boxButton'][$id_lang] = Tools::getValue('boxButton_' . $id_lang, Configuration::get('UPPSA_BOX_BUTTON', $id_lang)) ;
            }

            $dataAssociateGroups = [];
            for ($i = 0; $i < 5; $i++) {
                $dataKey = Tools::getValue('dataKey_' . $i);
                $dataValue = Tools::getValue('dataValue_' . $i);
                $dataSeparator = Tools::getValue('dataSeparator_' . $i);
                $dataGroup = Tools::getValue('dataGroup_' . $i);
                if ($dataKey && $dataValue && $dataGroup) {
                    $dataAssociateGroups[] = [
                        'key' => $dataKey,
                        'value' => $dataValue,
                        'separator' => $dataSeparator,
                        'id_group' => $dataGroup,
                    ];
                }
            }

            Configuration::updateValue('UPPSA_BOX_TITLE', $datas['boxTitle']);
            Configuration::updateValue('UPPSA_BOX_TEXT', $datas['boxText']);
            Configuration::updateValue('UPPSA_BOX_BUTTON', $datas['boxButton']);
            Configuration::updateValue('UPPSA_DATA_ASSOCIATE_GROUPS', json_encode($dataAssociateGroups));
        }

        return $this->renderForm();
    }

    public function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Admin.Global'),
                    'icon' => 'icon-cogs',
                ],
                'tabs' => [
                    'general' => $this->trans('Global configuration', [], 'Modules.UppsAuthService.Admin'),
                    'box' => $this->trans('Box configuration', [], 'Modules.UppsAuthService.Admin'),
                    'groups' => $this->trans('Groups management', [], 'Modules.UppsAuthService.Admin'),
                ],
                'input' => [
                    // Global configuration
                    [
                        'type' => 'radio',
                        'label' => $this->trans('Authentification mode', [], 'Modules.UppsAuthService.Admin'),
                        'name' => 'authentificationType',
                        'values' => [
                            [
                                'id' => 'shibboleth',
                                'value' => 'shibboleth',
                                'label' => $this->trans('Shibboleth', [], 'Modules.UppsAuthService.Admin'),
                            ],
                            /* @todo
                             * [
                             * 'id' => 'cas',
                             * 'value' => 'cas',
                             * 'label' => $this->trans('CAS', [], 'Modules.UppsAuthService.Admin'),
                             * ],
                             * [
                             * 'id' => 'ldap',
                             * 'value' => 'ldap',
                             * 'label' => $this->trans('LDAP', [], 'Modules.UppsAuthService.Admin'),
                             * ],
                             */
                        ],
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Shibboleth login url', [], 'Modules.UppsAuthService.Admin'),
                        'name' => 'shibLogin',
                        'class' => 'fixed-width-xxl',
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Shibboleth logout url', [], 'Modules.UppsAuthService.Admin'),
                        'name' => 'shibLogout',
                        'class' => 'fixed-width-xxl',
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Shibboleth country code', [], 'Modules.UppsAuthService.Admin'),
                        'name' => 'shibCountryCode',
                        'class' => 'fixed-width-xxl',
                        'tab' => 'general',
                        'desc' => $this->trans('Example: FRANCE=8;SPAIN=x', [], 'Modules.UppsAuthService.Admin'),
                    ],

                    // Box configuration
                    [
                        'type' => 'text',
                        'label' => $this->trans('Title', [], 'Modules.UppsAuthService.Admin'),
                        'name' => 'boxTitle',
                        'lang' => true,
                        'tab' => 'box',
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->trans('Text', [], 'Modules.UppsAuthService.Admin'),
                        'name' => 'boxText',
                        'cols' => 40,
                        'rows' => 10,
                        'class' => 'rte',
                        'autoload_rte' => true,
                        'lang' => true,
                        'tab' => 'box',
                    ],
                    [
                        'type' => 'html',
                        'name' => 'html_data',
                        'html_content' => '<div class="module_warning alert alert-warning">' . $this->trans('Veuillez saisir ici les variables permettant l\'affectation à un groupe.', [], 'Modules.UppsAuthService.Admin') . '</div>',
                        'tab' => 'groups',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Label of button', [], 'Modules.UppsAuthService.Admin'),
                        'name' => 'boxButton',
                        'lang' => true,
                        'tab' => 'box',
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];

        // Groups management
        for ($i = 0; $i < 5; $i++) {
            $fields_form['form']['input'][] = [
                'type' => 'html',
                'name' => 'html_data',
                'html_content' => '<br /><h4>' . $this->trans('Nouvelle variable', [], 'Modules.UppsAuthService.Admin') . '</h4><hr />',
                'tab' => 'groups',
            ];
            $fields_form['form']['input'][] = [
                'type' => 'text',
                'label' => $this->trans('Data key', [], 'Modules.UppsAuthService.Admin'),
                'name' => 'dataKey_' . $i,
                'class' => 'fixed-width-md',
                'tab' => 'groups',
            ];
            $fields_form['form']['input'][] = [
                'type' => 'text',
                'label' => $this->trans('Data value', [], 'Modules.UppsAuthService.Admin'),
                'name' => 'dataValue_' . $i,
                'class' => 'fixed-width-md',
                'tab' => 'groups',
            ];
            $fields_form['form']['input'][] = [
                'type' => 'text',
                'label' => $this->trans('Data separator', [], 'Modules.UppsAuthService.Admin'),
                'name' => 'dataSeparator_' . $i,
                'class' => 'fixed-width-md',
                'tab' => 'groups',
                'desc' => $this->trans('If you have a multiple value', [], 'Modules.UppsAuthService.Admin'),
            ];
            $fields_form['form']['input'][] = [
                'type' => 'select',
                'label' => $this->trans('Group association', [], 'Modules.UppsAuthService.Admin'),
                'name' => 'dataGroup_' . $i,
                'class' => 'input-lg',
                'options' => [
                    'query' => Group::getGroups((int)$this->context->language->id),
                    'id' => 'id_group',
                    'name' => 'name',
                    'default' => [
                        'value' => '',
                        'label' => $this->trans('-- Choose --', [], 'Modules.UppsAuthService.Admin '),
                    ]
                ],
                'tab' => 'groups',
            ];
        }

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->submit_action = 'submitUpdate';
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function getConfigFieldsValues()
    {
        $language_ids = Language::getIDs(false);
        $datas = [];
        foreach ($language_ids as $id_lang) {
            $datas['boxTitle'][$id_lang] = Tools::getValue('boxTitle_' . $id_lang, Configuration::get('UPPSA_BOX_TITLE', $id_lang)) ;
            $datas['boxText'][$id_lang] = Tools::getValue('boxText_' . $id_lang, Configuration::get('UPPSA_BOX_TEXT', $id_lang)) ;
            $datas['boxButton'][$id_lang] = Tools::getValue('boxButton_' . $id_lang, Configuration::get('UPPSA_BOX_BUTTON', $id_lang)) ;
        }

        $dataAssociateGroups = Configuration::get('UPPSA_DATA_ASSOCIATE_GROUPS');
        if ($dataAssociateGroups) {
            $dataAssociateGroups = json_decode($dataAssociateGroups, true);
        }

        for ($i = 0; $i < 5; $i++) {
            $datas['dataKey_' . $i] = (!empty($dataAssociateGroups[$i]) ? $dataAssociateGroups[$i]['key'] : '');
            $datas['dataValue_' . $i] = (!empty($dataAssociateGroups[$i]) ? $dataAssociateGroups[$i]['value'] : '');
            $datas['dataSeparator_' . $i] = (!empty($dataAssociateGroups[$i]) ? $dataAssociateGroups[$i]['separator'] : '');
            $datas['dataGroup_' . $i] = (!empty($dataAssociateGroups[$i]) ? $dataAssociateGroups[$i]['id_group'] : '');
        }

        return array_merge($datas, [
            'authentificationType' => Tools::getValue('authentificationType', Configuration::get('UPPSA_AUTHENTICATION_TYPE')),
            'shibLogin' => Tools::getValue('shibLogin', Configuration::get('UPPSA_SHIB_LOGIN')),
            'shibLogout' => Tools::getValue('shibLogout', Configuration::get('UPPSA_SHIB_LOGOUT')),
            'shibCountryCode' => Tools::getValue('shibCountryCode', Configuration::get('UPPSA_SHIB_COUNTRY_CODE'))
        ]);
    }

    public function hookDisplayCustomerLoginFormAfter($params)
    {
        $type = Configuration::get('UPPSA_AUTHENTICATION_TYPE');
        switch ($type) {
            case 'shibboleth':
                $urlLogin = Configuration::get('UPPSA_SHIB_LOGIN');
                break;
            case 'cas':
                // @todo
                break;
            case 'ldap':
                // @todo
                break;
        }

        if (!empty($urlLogin)) {
            $this->context->smarty->assign([
                'boxTitle' => Configuration::get('UPPSA_BOX_TITLE', (int)$this->context->language->id),
                'boxText' => Configuration::get('UPPSA_BOX_TEXT', (int)$this->context->language->id),
                'boxButton' => Configuration::get('UPPSA_BOX_BUTTON', (int)$this->context->language->id),
                'urlLogin' => $urlLogin
            ]);

            return $this->display(__FILE__, 'views/templates/hook/DisplayCustomerLoginFormAfter.tpl');
        }
    }

    public function hookActionFrontControllerAfterInit($params)
    {
        $type = Configuration::get('UPPSA_AUTHENTICATION_TYPE');
        switch ($type) {
            case 'shibboleth':
                $auth = AuthServiceShibboleth::process($this->context, $this->ssoData);
                if (!$auth) {
                    $this->context->controller->errors[] = $this->trans(
                        'Can\'t connect at the moment, please try again later.',
                        [],
                        'Shop.Notifications.Error'
                    );
                }
                break;
            case 'cas':
                // @todo
                break;
            case 'ldap':
                // @todo
                break;
        }
    }

    public function hookActionCustomerLogoutAfter($params)
    {
        if ($type = AuthService::getTypeByCustomerId($params['customer']->id)) {
            switch ($type) {
                case 'shibboleth':
                    $urlLogout = Configuration::get('UPPSA_SHIB_LOGOUT');
                    break;
                case 'cas':
                    // @todo
                    break;
                case 'ldap':
                    // @todo
                    break;
            }

            if (!empty($urlLogout)) {
                Tools::redirect($urlLogout);
            }
        }
    }

    public function hookActionObjectCustomerDeleteAfter($params)
    {
        if (!empty($params['object']->id)) {
            Db::getInstance()->delete(AuthService::$definition['table'], 'id_customer='.(int)$params['object']->id);
        }
    }

    private function getSSOData()
    {
        // For Shibboleth
        $this->ssoData = $_SERVER;
        // dump($_SERVER);
        // die;
    }
}