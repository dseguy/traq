<?php
/*!
 * Traq
 * Copyright (C) 2009-2014 Jack Polgar
 * Copyright (C) 2012-2014 Traq.io
 * https://github.com/nirix
 * http://traq.io
 *
 * This file is part of Traq.
 *
 * Traq is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 3 only.
 *
 * Traq is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Traq. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Traq\Controllers;

use Radium\Action\View;
use Radium\Core\Load;
use Radium\Http\Request;
use Traq\Helpers\Notification;
use Traq\Models\User;

/**
 * User controller
 *
 * @author Jack P.
 * @since 3.0
 * @package Traq\Controllers
 */
class Users extends AppController
{
    public $before = [
        'new' => ['alreadyLoggedIn']
    ];

    /**
     * Handles the register page and account creation.
     */
    public function newAction()
    {
        if (!settings('allow_registration')) {
            return $this->show404();
        }

        $this->title($this->translate('register'));

        $this->set('user', new User);
    }

    /**
     * Create user.
     */
    public function createAction()
    {
        $this->view = 'Users/new';

        // Create a model with the data
        $user = new User([
            'username' => Request::$post['username'],
            'name'     => Request::$post['name'],
            'password' => Request::$post['password'],
            'email'    => Request::$post['email']
        ]);

        // Account activation
        if (settings('accountActivation')) {
            $user->generateActivationKey();
        }

        // Check if the model is valid
        if ($user->save()) {
            // Send validation email
            if (settings('accountActivation')) {
                die("We're supposed to send an account activation email,
                     but that part isn't completed yet.");
            }
            // Redirect to login page
            else {
                $this->redirectTo('login');
            }
        }

        $this->set('user', $user);
    }

    /**
     * Account validation.
     */
    public function action_validate($key)
    {
        $user = User::select()->where('options', '%"validation_key":"' . $key . '"%', 'LIKE')->exec()->fetch();
        $user->option('validation_key', null);
        $user->save();

        $this->render['view'] = 'users/login';
        View::set('validated', true);
    }

    /**
     * Forgot/Reset password page.
     */
    public function action_reset_password($key = null)
    {
        // Reset key provided?
        if ($key !== null) {
            // Find user
            if ($user = User::select()->where('options', '%"reset_password_key":"' . $key . '"%', 'LIKE')->exec()->fetch()) {
                // Generate new password
                $new_password = substr(random_hash(), 0, 10);

                // Set new password, clear reset key and save
                $user->set_password($new_password);
                $user->option('reset_password_key', null);
                $user->save();

                // Send data to the view
                View::set('password_reset', true);
                View::set('new_password', $new_password);
            }
        }
        // Find user and generate key
        else {
            // Check if the form has been submitted
            if (Request::method() == 'post') {
                // Generate key
                if ($user = User::find('username', Request::$post['username'])) {
                    // Generate reset key
                    $key = random_hash();

                    // Set reset key option
                    $user->option('reset_password_key', $key);
                    $user->save();

                    // Send email
                    Notification::send(
                        $user, // User object
                        l('notifications.password_reset.subject'),     // Subject
                        l('notifications.password_reset.message',    // Message
                            settings('title'), // Installation title
                            $user->name,       // Users name
                            $user->username,   // Users username
                            "http://" . $_SERVER['HTTP_HOST'] . Request::base("/login/resetpassword/{$key}"), // Reset password URL
                            $_SERVER['REMOTE_ADDR'] // IP of reset request
                        )
                    );
                    View::set('reset_email_sent', true);
                } else {
                    View::set('error', true);
                }
            }
        }
    }

    /**
     * Redirect to the front page if the user is logged in.
     */
    public function alreadyLoggedIn()
    {
        if (LOGGEDIN) {
            $this->redirectTo('root');
        }
    }
}
