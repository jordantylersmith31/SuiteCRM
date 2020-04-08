<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

/**
 * Class ResetPassword
 */
class ResetPassword
{
    /**
     * @param string|null $username
     * @param string|null $useremail
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function reset(?string $username, ?string $useremail): void
    {
        if (!defined('sugarEntry') || !sugarEntry) {
            die('Not A Valid Entry Point');
        }

        global $current_user;

        $mod_strings = return_module_language('', 'Users');
        $res = $GLOBALS['sugar_config']['passwordsetting'];

        $this->validateInput($username, $useremail, $mod_strings);

        $usr = $this->loadUser($username, $useremail, $mod_strings);

        $password = User::generatePassword();

        $emailTemp_id = $res['generatepasswordtmpl'];

        $additionalData = array(
            'password' => $password
        );

        $result = $usr->sendEmailForPassword($emailTemp_id, $additionalData);

        $this->handleResult($result, $current_user, $mod_strings);
    }

    /**
     * @param string|null $username
     * @param string|null $useremail
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function sendResetLink(?string $username, ?string $useremail): void
    {
        if (!defined('sugarEntry') || !sugarEntry) {
            die('Not A Valid Entry Point');
        }

        global $current_user;

        $mod_strings = return_module_language('', 'Users');
        $res = $GLOBALS['sugar_config']['passwordsetting'];

        $this->validateInput($username, $useremail, $mod_strings);

        $usr = $this->loadUser($username, $useremail, $mod_strings);

        $url = $this->generateLink($username, $usr);

        $emailTemp_id = $res['lostpasswordtmpl'];

        $additionalData = array(
            'link' => true,
            'password' => ''
        );

        if (isset($url)) {
            $additionalData['url'] = $url;
        }

        $result = $usr->sendEmailForPassword($emailTemp_id, $additionalData);

        $this->handleResult($result, $current_user, $mod_strings);
    }

    /**
     * @param string $username
     * @param string $useremail
     * @param array $mod_strings
     */
    protected function validateInput(?string $username, ?string $useremail, array $mod_strings): void
    {
        if (empty($username) || empty($useremail)) {
            throw new InvalidArgumentException($mod_strings['LBL_PROVIDE_USERNAME_AND_EMAIL']);
        }
    }

    /**
     * @param string|null $useremail
     * @param User|null $user
     * @param array $mod_strings
     */
    protected function validateUser(?string $useremail, ?User $user, array $mod_strings): void
    {
        if (!$user->isPrimaryEmail($useremail)) {
            throw new InvalidArgumentException($mod_strings['LBL_PROVIDE_USERNAME_AND_EMAIL']);
        }

        if ($user->portal_only || $user->is_group) {
            throw new InvalidArgumentException($mod_strings['LBL_PROVIDE_USERNAME_AND_EMAIL']);
        }

        $regexmail = "/^\w+(['\.\-\+]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,})+\$/";

        if (!preg_match($regexmail, $user->emailAddress->getPrimaryAddress($user))) {
            throw new InvalidArgumentException($mod_strings['ERR_EMAIL_INCORRECT']);
        }
    }

    /**
     * @param string $type
     * @param string $message
     */
    protected function logError(string $type, string $message): void
    {
        global $log;

        $log->$type('ResetPassword: ' . $message);
    }

    /**
     * Load user
     * @param string|null $username
     * @param string|null $useremail
     * @param array $mod_strings
     * @return User
     */
    protected function loadUser(?string $username, ?string $useremail, array $mod_strings): User
    {
        $usr = new User();

        $usr_id = $usr->retrieve_user_id($username);
        $usr->retrieve($usr_id);

        $this->validateUser($useremail, $usr, $mod_strings);

        return $usr;
    }

    /**
     * @param array $result
     * @param User|null $current_user
     * @param array $mod_strings
     */
    protected function handleResult(array $result, ?User $current_user, array $mod_strings): void
    {
        if ($result['status'] === true) {
            return;
        }

        if ($result['status'] === false && !empty($result['message'])) {
            $this->logError('error', $result['message']);
            throw new BadFunctionCallException($result['message']);
        }

        if ($current_user->is_admin) {
            $email_errors = $mod_strings['ERR_EMAIL_NOT_SENT_ADMIN'];
            $email_errors .= "\n-" . $mod_strings['ERR_RECIPIENT_EMAIL'];
            $email_errors .= "\n-" . $mod_strings['ERR_SERVER_STATUS'];

            $this->logError('error', $email_errors);

            throw new BadFunctionCallException($email_errors);
        }

        $this->logError('error', $mod_strings['LBL_EMAIL_NOT_SENT']);
        throw new BadFunctionCallException($mod_strings['LBL_EMAIL_NOT_SENT']);
    }

    /**
     * Generate Link
     * @param string|null $username
     * @param User $usr
     * @return string
     */
    protected function generateLink(?string $username, User $usr): string
    {
        global $timedate;
        $guid = create_guid();
        $url = $GLOBALS['sugar_config']['site_url'] . "/index.php?entryPoint=Changenewpassword&guid=$guid";
        $time_now = TimeDate::getInstance()->nowDb();
        $q = "INSERT INTO users_password_link (id, username, date_generated) VALUES('" . $guid . "','" . $username . "','" . $time_now . "') ";
        $usr->db->query($q);

        return $url;
    }
}