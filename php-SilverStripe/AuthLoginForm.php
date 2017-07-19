<?php

/**
 * Overriding the base login form to redirect somewhere useful,
 * in this case to the site index
 */
class AuthLoginForm extends MemberLoginForm
{

    private static $allowed_actions = array('dologin', 'logout');

    public function dologin($data)
    {
        if ($this->performLogin($data))
        {
            Rest::generateAccessToken(Member::currentUser(), $data['Password']);
            if (Member::currentUserID())
            {                
                if (Member::currentUser()->Type == MemberExtension::TYPE_ADMIN)
                {                    
                    $this->controller->redirect(Director::absoluteURL('/admin/pages'));
                }
                elseif (Member::currentUser()->Type == MemberExtension::TYPE_EMPLOYER)
                {
                    $this->controller->redirect(Director::absoluteURL('/employer-dashboard'));
                }
                elseif (Member::currentUser()->Type == MemberExtension::TYPE_EMPLOYEE)
                {
                    $response = Rest::sendRequest('getcurrentmember');
                    if (!$response['result']['found'])
                        $this->controller->redirect(Director::absoluteURL('/employee-form'));
                    else if (!empty($response['result']['plan_id']) && $response['result']['plan_feature_selected'])
                        $this->controller->redirect(Director::absoluteURL('/employee-plan-information'));
                    else if (!empty($response['result']['plan_id']))
                        $this->controller->redirect(Director::absoluteURL('/more-options'));
                    else if ($response['result']['enrollment_status'] == 'declined')
                        $this->controller->redirect(Director::absoluteURL('/employee-declined'));
                    else if ($response['result']['employee_can_select_plan'])
                        $this->controller->redirect(Director::absoluteURL('/recommended-plan'));
                    else
                        $this->controller->redirect(Director::absoluteURL('/employee-wait'));
                }
                else
                {
                    $this->controller->redirect(Director::baseURL());
                }
            }
        }
        else
        {
            if (array_key_exists('Email', $data))
            {
                Session::set('SessionForms.MemberLoginForm.Email', $data['Email']);
                Session::set('SessionForms.MemberLoginForm.Remember', isset($data['Remember']));
            }

            if (isset($_REQUEST['BackURL']))
                $backURL = $_REQUEST['BackURL'];
            else
                $backURL = null;

            if ($backURL)
                Session::set('BackURL', $backURL);

            // Show the right tab on failed login
            $loginLink = Director::absoluteURL($this->controller->Link('login'));
            if ($backURL)
                $loginLink .= '?BackURL=' . urlencode($backURL);
            $this->controller->redirect($loginLink . '#' . $this->FormName() . '_tab');
        }
    }

}
