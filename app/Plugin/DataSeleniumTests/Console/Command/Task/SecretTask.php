<?php
/**
 * Insert Secret Task
 *
 * @copyright    copyright 2012 Passbolt.com
 * @license      http://www.passbolt.com/license
 * @package      app.plugins.DataExtras.Console.Command.Task.SecretTask
 * @since        version 2.12.11
 */

require_once(ROOT . DS . APP_DIR . DS . 'Console' . DS . 'Command' . DS . 'Task' . DS . 'ModelTask.php');

App::uses('Secret', 'Model');
App::uses('Resource', 'Model');
App::uses('User', 'Model');
App::uses('Gpgkey', 'Model');

class SecretTask extends ModelTask {

    public $model = 'Secret';

    /**
     * Get Dummy Secret Data.
     *
     * The passwords are always returned in the same order, useful for cross checking.
     * This secret was encrypted with the dummy public key, also located in the repository.
     *
     * @return string
     */
    protected function getDummyPassword() {
        static $i = 0;
        $passwords = array(
            "testpassword",
            "123456",
            "qwerty",
            "111111",
            "iloveyou",
            "adbobe123",
            "admin",
            "letmein",
            "monkey",
            "adobe",
            "sunshine",
            "princess",
            "azerty",
            "trustno1",
            "iamgod",
            "love",
            "god",
            "business",
            "passbolt",
            "enova",
            "kevisthebest",
        );
        $password = $passwords[$i];
        $i++;
        if ($i > sizeof($passwords) - 1) {
            $i = 0;
        }
        return $passwords[$i];
    }

    /**
     * Encrypt a password with the user public key.
     * @param $password
     * @param $userId
     * @return string $encrypted encrypted password
     */
    protected function encryptPassword($password, $userId) {
        $GpgkeyTask = $this->Tasks->load('Data.Gpgkey');
        $gpgkeyPath = $GpgkeyTask->getGpgkeyPath($userId);
        $Gpgkey = Common::getModel('Gpgkey');
        $key = $Gpgkey->find("first", array('conditions' => array(
            'Gpgkey.user_id' => $userId,
            'Gpgkey.deleted' => 0
        )));

        $res = gnupg_init();
        gnupg_import($res, $key['Gpgkey']['key']);
        gnupg_addencryptkey($res , $key['Gpgkey']['fingerprint']);
        $encrypted = gnupg_encrypt($res , $password);

        return $encrypted;
    }

    /**
     * Get a password for a given resource, get a random one if not found / unspecified
     * @param null $resource id
     * @return string password
     */
    protected function getPassword($resource = null) {
        switch ($resource) {
            // Apache = very strong
            case Common::uuid('resource.id.apache') :
                return '_upjvh-p@wAHP18D}OmY05M';
            // April = strong
            case Common::uuid('resource.id.april') :
                return 'z"(-1s]3&Itdno:vPt';
            // Bower = fair
            case Common::uuid('resource.id.bower') :
                return 'CL]m]x(o{sA#QW';
            // Bower = this_23-04
            case Common::uuid('resource.id.centos') :
                return 'this_23-04';
            // Centos = very weak
            default :
                return $this->getDummyPassword();
        }
    }

    /**
     * Get all Secret data.
     * @return array
     */
    protected function getData() {
        $Resource = Common::getModel('Resource');
        $User = Common::getModel('User');
        $rs = $Resource->find('all');
        $us = $User->find('all');

        // Insertion for all users who can access to available resources.
        // We insert dummy data, same secret for everyone.
        $s = [];
        foreach($rs as $r) {
            $password = $this->getPassword($r['Resource']['id']);
            //echo  $r['Resource']['name'] . ':' . $password . "\n";
            foreach ($us as $u) {
                $isAuthorized = $Resource->isAuthorized($r['Resource']['id'], PermissionType::READ, $u['User']['id']);
                if ($isAuthorized) {
                    $passwordEncrypted = $this->encryptPassword($password, $u['User']['id']);
                    $s[] = array('Secret'=>array(
                        'id' => Common::uuid(),
                        'user_id' => $u['User']['id'],
                        'resource_id' => $r['Resource']['id'],
                        'data' => $passwordEncrypted,
                        'created_by' => $u['User']['id']
                    ));
                }
            }
        }
        return $s;
    }
}