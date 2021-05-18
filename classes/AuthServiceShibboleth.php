<?php

class AuthServiceShibboleth extends AuthService
{
    public $type = 'shibboleth';
    private static $authKey = 'HTTP_EPPN';

    private static function getCustomerIdByAuthKey($auth_key)
    {
        return (int)Db::getInstance()->getValue('
            SELECT a.`id_customer`
            FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` a
            INNER JOIN `' . _DB_PREFIX_ . Customer::$definition['table'] . '` c ON (a.`id_customer` = c.`id_customer`) 
            WHERE a.`auth_key` = "' . pSQL($auth_key) . '"           
        ');
    }

    public static function process($context, $ssoData)
    {
        if ($context->customer->isLogged()) {
            if (!$ssoData['HTTP_SHIB_SESSION_ID']) {
                $context->customer->logout();
                Tools::redirect('index');
            }
        } else {
            if (!empty($ssoData[self::$authKey])) {
                $id_customer = self::getCustomerIdByAuthKey($ssoData[self::$authKey]);
                if (!$id_customer) {
                    $customer = new Customer();
                    $customer->email = $ssoData['HTTP_MAIL'];
                    $customer->passwd = uniqid();
                    $customer->lastname = $ssoData['HTTP_SN'];
                    $customer->firstname = $ssoData['HTTP_GIVENNAME'];

                    switch ($ssoData['HTTP_SUPANNCIVILITE']) {
                        case 'M.':
                            $customer->id_gender = 1;
                            break;
                        case 'Mme':
                            $customer->id_gender = 2;
                            break;
                    }
                    if (!empty($ssoData['HTTP_SCHACDATEOFBIRTH'])) {
                        $birthDate = DateTime::createFromFormat('Ymd', $ssoData['HTTP_SCHACDATEOFBIRTH']);
                        if (ValidateCore::isBirthDate($birthDate->format('Y-m-d'))) {
                            $customer->birtdate = $birthDate->format('Y-m-d');
                        }
                    }

                    if (($error = $customer->validateFields(false, true)) !== true) {
                        AuthServiceLog::addLog('Error create customer :' . print_r($error, true));
                        return false;
                    } else {
                        $customer->add();
                    }

                    // Get Countries ID
                    $shibCountryCode = Configuration::get('UPPSA_SHIB_COUNTRY_CODE');
                    if ($shibCountryCode) {
                        $countries = [];
                        $shibCountryCode = explode(';', $shibCountryCode);
                        foreach ($shibCountryCode as $countryCode) {
                            $explodeCountryCode = explode('=', $countryCode);
                            if (count($explodeCountryCode) == 2 && $explodeCountryCode[1] > 0) {
                                $countries[$explodeCountryCode[0]] = (int)$explodeCountryCode[1];
                            }
                        }
                    }

                    if (!empty($countries) && !empty($ssoData['HTTP_POSTALADDRESS'])) {
                        $explodeAddress = explode('$', $ssoData['HTTP_POSTALADDRESS']);
                        if (count($explodeAddress) == 4) {
                            $address1 = $explodeAddress[0];
                            $address2 = $explodeAddress[1];
                            $postcode = substr($explodeAddress[2], 0, 5);
                            $city = trim(str_replace($postcode, '', $explodeAddress[2]));
                            $country = $explodeAddress[3];
                        } else {
                            $address1 = $explodeAddress[0];
                            $address2 = '';
                            $postcode = substr($explodeAddress[1], 0, 5);
                            $city = trim(str_replace($postcode, '', $explodeAddress[1]));
                            $country = $explodeAddress[2];
                        }

                        $id_country = (isset($countries[$country]) ? $countries[$country] : 0);
                        if ($id_country) {
                            $address = new Address();
                            $address->alias = 'Mon addresse';
                            $address->lastname = $ssoData['HTTP_SN'];
                            $address->firstname = $ssoData['HTTP_GIVENNAME'];
                            $address->id_customer = $customer->id;
                            $address->id_country = (int)$id_country;
                            $address->city = $city;
                            $address->postcode = $postcode;
                            $address->phone = $ssoData['HTTP_TELEPHONENUMBER'];
                            // $address->phone_mobile = $ssoData['HTTP_PAGER'];
                            $address->address1 = $address1;
                            $address->address2 = $address2;

                            if (($error = $address->validateFields(false, true)) !== true) {
                                AuthServiceLog::addLog('Error create address :' . print_r($error, true));
                                return false;
                            } else {
                                $address->add();
                            }
                        }
                    }

                    $authService = new AuthServiceShibboleth();
                    $authService->auth_key = $ssoData[self::$authKey];
                    $authService->id_customer = $customer->id;
                    $authService->id_address = (!empty($address->id) ? $address->id : null);

                    if (($error = $authService->validateFields(false, true)) !== true) {
                        AuthServiceLog::addLog('Error create auth :' . print_r($error, true));
                        return false;
                    } else {
                        $authService->add();
                    }

                    $context->updateCustomer($customer);
                    AuthService::addGroupsToCustomer((int)$customer->id, $ssoData);

                    return true;
                } else {
                    $customer = new Customer((int)$id_customer);
                    AuthService::addGroupsToCustomer((int)$id_customer, $ssoData);

                    if (!$customer->active) {
                        $errors[] = self::getTrans('Your account isn\'t available at this time, please contact us', [], 'Shop.Notifications.Error');
                    } elseif (!$customer->id || $customer->is_guest) {
                        $errors[] = self::getTrans('Authentication failed.', [], 'Shop.Notifications.Error');
                    } else {
                        $context->updateCustomer($customer);
                        return true;
                    }
                    return false;
                }
            }
        }

        return true;
    }
}