<?php

class AccountController extends BaseController
{

    public function createAccountView()
    {
        return View::make('account/create');
    }

    public function createAccountSubmit()
    {
        $rules = array(
            "username" => "required|min:6",
            "password" => "required|min:6",
            "confirm_password" => "required|same:password",
            "email" => "required|email",
        );

        $validator = Validator::make(Input::all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->messages();

            return Redirect::to("create")
                ->withInput(Input::except('password', 'password_confirm'))
                ->withErrors($validator);
        }

        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $username = $_POST['username'];
        $password = $_POST['password'];
        $email = $_POST['email'];

        //Fixme - Save these user information
//        $organization = $_POST['organization'];
//        $address = $_POST['address'];
//        $country = $_POST['country'];
//        $telephone = $_POST['telephone'];
//        $mobile = $_POST['mobile'];
//        $im = $_POST['im'];
//        $url = $_POST['url'];
        $organization = "";
        $address = "";
        $country = "";
        $telephone = "";
        $mobile = "";
        $im = "";
        $url = "";


        if (WSIS::usernameExists($username)) {
            return Redirect::to("create")
                ->withInput(Input::except('password', 'password_confirm'))
                ->with("username_exists", true);
        } else {
            WSIS::addUser($username, $password, $first_name, $last_name, $email, $organization,
                $address, $country, $telephone, $mobile, $im, $url);

            //update user profile
            WSIS::updateUserProfile($username, $email, $first_name, $last_name);

            CommonUtilities::print_success_message('New user created!');
            return View::make('account/login');
        }
    }

    public function loginView()
    {
        if(Config::get('pga_config.wsis')['auth-mode'] == "oauth"){
            $url = WSIS::getOAuthRequestCodeUrl();
            return Redirect::away($url);
        }else{
            return View::make('account/login');
        }
    }

    public function oauthCallback()
    {
        if (!isset($_GET["code"])) {
            CommonUtilities::print_error_message("Require the code parameter to validate!");
        }

        $code = $_GET["code"];
        $response = WSIS::getOAuthToken($code);
        $accessToken = $response->access_token;
        $refreshToken = $response->refresh_token;
        $expirationTime = time() + $response->expires_in - 5; //5 seconds safe margin
        $authzToken = new Airavata\Model\Security\AuthzToken();
        $authzToken->accessToken = $accessToken;
        Session::put('authz-token',$authzToken);
        Session::put('oauth-refresh-code',$refreshToken);
        Session::put('oauth-expiration-time',$expirationTime);

        $userProfile = WSIS::getUserProfileFromOAuthToken($accessToken);
        Session::put("user-profile", $userProfile);

        $userRoles = $userProfile['roles'];
        if (in_array(Config::get('pga_config.wsis')['admin-role-name'], $userRoles)) {
            Session::put("admin", true);
        }
        if (in_array(Config::get('pga_config.wsis')['read-only-admin'], $userRoles)) {
            Session::put("admin-read-only", true);
        }

        $username = $userProfile['username'];
        CommonUtilities::store_id_in_session($username);
        CommonUtilities::print_success_message('Login successful! You will be redirected to your home page shortly.');
        Session::put("gateway_id", Config::get('pga_config.airavata')['gateway-id']);

        //creating a default project for user
        $projects = ProjectUtilities::get_all_user_projects(Config::get('pga_config.airavata')['gateway-id'], $username);
        if($projects == null || count($projects) == 0){
            //creating a default project for user
            ProjectUtilities::create_default_project($username);
        }

        return Redirect::to("home");
    }

    public function loginSubmit()
    {

        if (CommonUtilities::form_submitted()) {
            $username = $_POST['username'];
            $password = $_POST['password'];
            try {
                if (WSIS::authenticate($username, $password)) {
                    $userRoles = (array)WSIS::getUserRoles($username);
                    if (in_array(Config::get('pga_config.wsis')['admin-role-name'], $userRoles)) {
                        Session::put("admin", true);
                    }
                    if (in_array(Config::get('pga_config.wsis')['read-only-admin'], $userRoles)) {
                        Session::put("admin-read-only", true);
                    }

                    $userProfile = WSIS::getUserProfile($username);
                    if($userProfile != null && !empty($userProfile)){
                        Session::put("user-profile", $userProfile);
                    }

                    CommonUtilities::store_id_in_session($username);
                    CommonUtilities::print_success_message('Login successful! You will be redirected to your home page shortly.');
                    Session::put("gateway_id", Config::get('pga_config.airavata')['gateway-id']);

                    //creating a default project for user
                    $projects = ProjectUtilities::get_all_user_projects(Config::get('pga_config.airavata')['gateway-id'], $username);
                    if($projects == null || count($projects) == 0){
                        //creating a default project for user
                        ProjectUtilities::create_default_project($username);
                    }

                    return Redirect::to("home");

                } else {
                    return Redirect::to("login")->with("invalid-credentials", true);
                }
            } catch (Exception $ex) {
                return Redirect::to("login")->with("invalid-credentials", true);
            }
        }

    }

    public function forgotPassword()
    {
        return View::make("account/forgot-password");
    }

    public function forgotPasswordSubmit()
    {
        $username = Input::get("username");
        if(empty($username)){
            CommonUtilities::print_error_message("Please provide a valid username");
            return View::make("account/forgot-password");
        }else{
            $username = $username . "@" . explode("@",Config::get('pga_config.wsis')['admin-username'])[1];
            try{
                $key = WSIS::validateUser($username);
                if(!empty($key)){
                    $result = WSIS::sendPasswordResetNotification($username, $key);
                    if($result===true){
                        CommonUtilities::print_success_message("Password reset notification was sent to your email account");
                        return View::make("home");
                    }else{
                        CommonUtilities::print_error_message("Failed to send password reset notification email");
                        return View::make("home");
                    }
                }else{
                    CommonUtilities::print_error_message("Failed to validate the given username");
                    return View::make("account/forgot-password");
                }
            }catch (Exception $ex){
                CommonUtilities::print_error_message("Password reset operation failed");
                return View::make("home");
            }
        }
    }

    public function resetPassword()
    {
        $confirmation = Input::get("confirmation");
        $username = Input::get("username");
        if(empty($username) || empty($confirmation)){
            return View::make("home");
        }else{
            $username = $username . "@" . explode("@",Config::get('pga_config.wsis')['admin-username'])[1];
            try{
                $key = WSIS::validateConfirmationCode($username, $confirmation);
                if(!empty($key)){
                    return View::make("account/reset-password", array("key" => $key, "username"=>$username));
                }else{
                    return View::make("home");
                }
            }catch (Exception $e){
                return View::make("home");
            }
        }

    }

    public function resetPasswordSubmit()
    {
        $rules = array(
            "new_password" => "required|min:6",
            "confirm_new_password" => "required|same:new_password",
        );

        $validator = Validator::make(Input::all(), $rules);
        if ($validator->fails()) {
            return Redirect::to("reset-password")
                ->withInput(Input::except('new_password', 'confirm)new_password'))
                ->withErrors($validator);
        }

        $key =  $_POST['key'];
        $username =  $_POST['username'];
        $new_password =  $_POST['new_password'];

        try{
            $result = WSIS::resetPassword($username, $new_password, $key);
            if($result){
                CommonUtilities::print_success_message("User password was reset successfully");
                return View::make("account/login");
            }else{
                CommonUtilities::print_error_message("Resetting user password operation failed");
                return View::make("account/home");
            }
        }catch (Exception $e){
            CommonUtilities::print_error_message("Resetting user password operation failed");
            return View::make("account/home");
        }
    }


    public function logout()
    {
        Session::flush();
        return Redirect::to('home');
    }

}
