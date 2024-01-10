<?php
/*
* Validate forgotten password
*/
/*class Myshipserv_Validate_ForgottenPasswordValidate
{

	public static function validate(array $params)
	{		
		$valid = true;
		$error1 = '';
		$error2 = '';
		$error3 = '';

		if (array_key_exists('username', $params)) {
			if (strlen($params['username']) === 0) {
				$error3 = 'Username/Email address is invalid or request ticket has expired.';
				$valid = false;
			} 
		} else {
			$valid = false;
			$error3 = 'Username/Email address is invalid or request ticket has expired.';
		}

		if (array_key_exists('password', $params)) {
			if (strlen($params['password']) === 0) {
				$error1 = 'Please enter a password.';
				$valid = false;
			} else if (strlen($params['password']) < 6) {
				$valid = false;
				$error1 = 'Your password must be at least 6 characters long.';
			}
		} else {
			$valid = false;
			$error1 = 'Please enter a password.';
		}

		if (array_key_exists('confirmPassword', $params)) {
			if (strlen($params['password']) === 0) {
				$error2 = 'Please enter a password confirmation.';
				$valid = false;
			} else if (strlen($params['confirmPassword']) < 6) {
				$valid = false;
				$error2 = 'Your password must be at least 6 characters long.';
			}
		} else {
			$valid = false;
			$error2 = 'Please enter a password confirmation.';
		}
		
		if ($valid === true) {
			if ($params['password'] !== $params['confirmPassword']) {
				$error1 = 'Password confirmation does not match.';
				$error2 = 'Password confirmation does not match.';
				$valid = false;
			}	
		}

		return array(
				'valid' => $valid,
				'error1' => $error1,
				'error2' => $error2,
				'error3' => $error3,
				'general' => ''
			);
	}

	public static function valid()
	{
		return array(
				'valid' => true,
				'error1' => '',
				'error2' => '',
				'error3' => '',
				'general' => ''
			);
	}
}*/
class Myshipserv_Validate_ForgottenPasswordValidate
{
   public static function validate(array $params)
   {
       $valid = true;
       $error1 = '';
       $error2 = '';
       $error3 = '';

       if (array_key_exists('username', $params)) {
           if (strlen($params['username']) === 0) {
               $error3 = 'Username/Email address is invalid or request ticket has expired.';
               $valid = false;
           }
       } else {
           $valid = false;
           $error3 = 'Username/Email address is invalid or request ticket has expired.';
       }

       if (array_key_exists('password', $params)) {
           if (strlen($params['password']) === 0) {
               $error1 = 'Please enter a password.';
               $valid = false;
           } else if (strlen($params['password']) < 8) {
               $valid = false;
               $error1 = 'Minimum of 8 characters password containing a combination of uppercase and lowercase letter and special character.';
           } else if (!preg_match('/[A-Z]/', $params['password'])) {
               $valid = false;
               $error1 = 'Minimum of 8 characters password containing a combination of uppercase and lowercase letter and special character.';
           } else if (!preg_match('/[a-z]/', $params['password'])) {
               $valid = false;
               $error1 = 'Minimum of 8 characters password containing a combination of uppercase and lowercase letter and special character.';
           } else if (!preg_match('/[!@#$%^&*()\[\]{};:<>?~\-_+=|\/]/', $params['password'])) {
               $valid = false;
               $error1 = 'Minimum of 8 characters password containing a combination of uppercase and lowercase letter and special character.';
           }
       } else {
           $valid = false;
           $error1 = 'Please enter a password.';
       }

       if (array_key_exists('confirmPassword', $params)) {
           if (strlen($params['confirmPassword']) === 0) {
               $error2 = 'Please enter a password confirmation.';
               $valid = false;
           } else if (strlen($params['confirmPassword']) < 8) {
               $valid = false;
               $error2 = 'Minimum of 8 characters password containing a combination of uppercase and lowercase letter and special character.';
           }
       } else {
           $valid = false;
           $error2 = 'Please enter a password confirmation.';
       }

       if ($valid === true) {
           if ($params['password'] !== $params['confirmPassword']) {
               $error1 = 'Password confirmation does not match.';
               $error2 = 'Password confirmation does not match.';
               $valid = false;
           }
       }

       return array(
           'valid' => $valid,
           'error1' => $error1,
           'error2' => $error2,
           'error3' => $error3,
           'general' => ''
       );
   }

   public static function valid()
   {
       return array(
           'valid' => true,
           'error1' => '',
           'error2' => '',
           'error3' => '',
           'general' => ''
       );
   }
}
