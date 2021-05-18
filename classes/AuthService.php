<?php

class AuthService extends ObjectModel
{
    public $type;
    public $auth_key;
    public $id_customer;
    public $id_address;
    public $date_add;
    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'authservice',
        'primary' => 'id_authservice',
        'fields' => [
            'type' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'auth_key' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 128],
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId'],
            'id_address' => ['type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false],
        ],
    ];

    public static function getTypeByCustomerId($id_customer)
    {
        return Db::getInstance()->getValue('
            SELECT `type`
            FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
            WHERE `id_customer` = ' . (int)$id_customer . '           
        ');
    }

    public static function addGroupsToCustomer($id_customer, $ssoData)
    {
        $dataAssociateGroups = Configuration::get('UPPSA_DATA_ASSOCIATE_GROUPS');
        if ($dataAssociateGroups) {
            $dataAssociateGroups = json_decode($dataAssociateGroups, true);
        }

        if (empty($dataAssociateGroups)) {
            return;
        }

        $groups = [];
        foreach ($dataAssociateGroups as $dataAssociateGroup) {
            if (empty($ssoData[$dataAssociateGroup['key']])) {
                continue;
            }

            if (!empty($dataAssociateGroup['separator'])) {
                $values = explode($dataAssociateGroup['separator'], $ssoData[$dataAssociateGroup['key']]);
            } else {
                $values = [$ssoData[$dataAssociateGroup['key']]];
            }

            foreach ($values as $value) {
                if ($value == $dataAssociateGroup['value']
                    && !in_array((int)$dataAssociateGroup['id_group'], $groups)
                ) {
                    $groups[] = (int)$dataAssociateGroup['id_group'];
                }
            }
        }

        if (empty($groups)) {
            return;
        }

        $customer = new Customer((int)$id_customer);
        $customer->addGroups($groups);
    }
}