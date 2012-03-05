FuelPHP Evernote package
========================

How to use
----------
1. Add an Evernote package to package directory as a git submodule.
2. Download the [Evernote API SDK](http://www.evernote.com/about/developer/api/). And extract it.
3. Make fuel/app/vendor/evernote dir.
4. Copy the PHP SDK lib directory to that evernote vendor directory. (fuel/app/vendor/evernote/lib)
5. Write login action as this code

        public function action_login()
        {
            $action = \Fuel\Core\Input::get('action');

            if ($action === null)
            {
                $success = \Evernote\Evernote::getTemporaryCredentials();
                if ($success) {
                    \Fuel\Core\Response::redirect(\Evernote\Evernote::getAuthorizationUrl(\Fuel\Core\Session::get('requestToken')));
                }
            }
            elseif ($action === 'callback')
            {
                if (\Evernote\Evernote::handleCallback())
                {
                    $credentials = \Evernote\Evernote::getTokenCredentials();
                    if ($credentials['status'] === 'success') {
                        \Fuel\Core\Session::set('access_token', $credentials['access_token']);
                        \Fuel\Core\Session::set('access_token_secret', $credentials['access_tokenSecret']);
                        \Fuel\Core\Session::set('shard_id', $credentials['shard_id']);
                        \Fuel\Core\Session::set('user_id', $credentials['user_id']);
                    }
                }

                \Fuel\Core\Response::redirect('index');
            }
        }
