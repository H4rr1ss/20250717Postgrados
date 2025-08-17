<?php

namespace Eep\Service;

use Eep\Service\UserManager;
use Zend\Authentication\Adapter\AdapterInterface;
use Zend\Authentication\Result;
use Zend\Crypt\Password\Bcrypt;
use Eep\Entity\User;

/**
 * Adapter used for authenticating user. It takes login and password on input
 * and checks the database if there is a user with such login (email) and password.
 * If such user exists, the service returns its identity (email). The identity
 * is saved to session and can be retrieved later with Identity view helper provided
 * by ZF3.
 */
class AuthAdapter implements AdapterInterface {

    private $id;
    private $password;
    private $userManager;

    public function __construct(UserManager $userManager) {
        $this->userManager = $userManager;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function setPassword($password) {
        $this->password = (string) $password;
    }

    public function authenticate() {
        //SEARCHING FOR USERS WITH SPECIFIED ID (CUI/REGISTRO ACADEMICO/PASAPORTE)
        $users = $this->userManager->getPossibleUsers($this->id);
        //NOT FOUND
        if ($users == null || count($users) === 0) {
            return new Result(Result::FAILURE_IDENTITY_NOT_FOUND, null, ['El número de identificación no está registrado']);
        }


        $authUsers = [];
        foreach ($users as $user) {
            $bcrypt = new Bcrypt();
            $passwordHash = $user->getContrasenia();

            if ($bcrypt->verify($this->password, $passwordHash)) {
                array_push($authUsers, $user);
            }
        }

        if (count($authUsers) > 0) {
            //MORE THAN ONE USER WITH THAT ID
            if (count($authUsers) >= 2) {
                return new Result(
                        Result::FAILURE_IDENTITY_AMBIGUOUS, null, ['Problema de autenticación, se requiere cambiar su contraseña. Ponerse en contacto con Control Académico para cambiar su contraseña']);
            }
            //AUTHENTICATED USER
            $user = $authUsers[0];
            //RETURNING THE USER ID FOR IT TO BE SAVED IN SESSION
            return new Result(
                    Result::SUCCESS, $user->getCode(), ['Usuario autenticado satisfactoriamente']);

        }

        // If password check didn't pass return 'Invalid Credential' failure status.
        return new Result(
                Result::FAILURE_CREDENTIAL_INVALID, null, ['Contraseña incorrecta']);
    }

}
