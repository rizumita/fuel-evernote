<?php

namespace Evernote;

require_once(APPPATH."vendor/evernote-sdk-php/lib/Thrift.php");
require_once(APPPATH."vendor/evernote-sdk-php/lib/transport/TTransport.php");
require_once(APPPATH."vendor/evernote-sdk-php/lib/transport/THttpClient.php");
require_once(APPPATH."vendor/evernote-sdk-php/lib/protocol/TProtocol.php");
require_once(APPPATH."vendor/evernote-sdk-php/lib/protocol/TBinaryProtocol.php");
require_once(APPPATH."vendor/evernote-sdk-php/lib/packages/Types/Types_types.php");
require_once(APPPATH."vendor/evernote-sdk-php/lib/packages/UserStore/UserStore.php");
require_once(APPPATH."vendor/evernote-sdk-php/lib/packages/NoteStore/NoteStore.php");

function getCallbackUrl()
{
    $thisUrl = \Fuel\Core\Input::server('HTTPS') ? "https://" : "http://";
    $thisUrl .= \Fuel\Core\Input::server('SERVER_NAME');
    $thisUrl .= (\Fuel\Core\Input::server('SERVER_PORT') == 80 || \Fuel\Core\Input::server('SERVER_PORT') == 443) ? "" : (":".\Fuel\Core\Input::server('SERVER_PORT'));
    $thisUrl .= '/index/login';
    $thisUrl .= '?action=callback';
    return $thisUrl;
}

class Evernote
{
    protected $access_token, $shard_id, $user_id;

    public static function _init()
    {
        \Fuel\Core\Config::load('evernote', true);
    }

    public function __construct($access_token, $shard_id, $user_id)
    {
        $this->access_token = $access_token;
        $this->shard_id = $shard_id;
        $this->user_id = $user_id;
    }

    public static function getTemporaryCredentials()
    {
        try
        {
            $request_token_url = \Fuel\Core\Config::get('evernote.evernote_server').'/oauth';
            $oauth = new \OAuth(\Fuel\Core\Config::get('evernote.consumer_key'), \Fuel\Core\Config::get('evernote.consumer_secret'));
            $request_token_info = $oauth->getRequestToken($request_token_url, getCallbackUrl());

            if ($request_token_info)
            {
                \Fuel\Core\Session::set('requestToken', $request_token_info['oauth_token']);
                \Fuel\Core\Session::set('requestTokenSecret', $request_token_info['oauth_token_secret']);
                return TRUE;
            }
        }
        catch (OAuthException $e)
        {
        }

        return false;
    }

    public static function getAuthorizationUrl($requestToken)
    {
        $url = \Fuel\Core\Config::get('evernote.evernote_server').DS.'/OAuth.action';
        $url .= '?oauth_token=';
        $url .= $requestToken;
        return $url;
    }

    public static function handleCallback()
    {
        $result = false;
        $oauth_verifier = \Fuel\Core\Input::get('oauth_verifier');

        if (isset($oauth_verifier))
        {
            \Fuel\Core\Session::set('oauthVerifier', $oauth_verifier);
            return true;
        }
        else
        {
            return false;
        }
    }

    public static function getTokenCredentials()
    {
        $result = array();

        try
        {
            $access_token_url = \Fuel\Core\Config::get('evernote.evernote_server').'/oauth';
            $oauth_verifier = \Fuel\Core\Session::get('oauthVerifier');
            $oauth = new \OAuth(\Fuel\Core\Config::get('evernote.consumer_key'), \Fuel\Core\Config::get('evernote.consumer_secret'));
            $request_token = \Fuel\Core\Session::get('requestToken');
            $request_token_secret = \Fuel\Core\Session::get('requestTokenSecret');

            $oauth->setToken($request_token, $request_token_secret);
            $access_token_info = $oauth->getAccessToken($access_token_url, null, $oauth_verifier);
            if ($access_token_info)
            {
                $result['status'] = 'success';
                $result['access_token'] = $access_token_info['oauth_token'];
                $result['access_token_secret'] = $access_token_info['oauth_token_secret'];
                $result['shard_id'] = $access_token_info['edam_shard'];
                $result['user_id'] = $access_token_info['edam_userId'];
            }
            else
            {
                $result['status'] = 'failure';
            }
        }
        catch (\OAuthException $e)
        {
            $result['status'] = 'failure';
        }

        return $result;
    }

    protected function note_store()
    {
        $note_store_transaction = new \THttpClient(\Fuel\Core\Config::get('evernote.notestore_host'), \Fuel\Core\Config::get('notestore_port'), '/edam/note/'.$this->shard_id, \Fuel\Core\Config::get('evernote.notestore_protocol'));
        $note_store_protocol = new \TBinaryProtocol($note_store_transaction);
        $note_store = new \NoteStoreClient($note_store_protocol, $note_store_protocol);
        return $note_store;
    }

    public function list_notebooks()
    {
        $result = array();

        try
        {
            $note_store = $this->note_store();

            $notebooks = $note_store->listNotebooks($this->access_token);
            $result['status'] = true;
            $result['result'] = array();

            if (!empty($notebooks))
            {
                foreach ($notebooks as $notebook)
                {
                    $result['result'][] = $notebook->name;
                }
            }
        }
        catch (\edam_error_EDAMSystemException $e)
        {
            $result['status'] = false;

            if (isset(\edam_error_EDAMErrorCode::$__names[$e->errorCode]))
            {
                $result['message'] = 'Error listing notebooks: '.\edam_error_EDAMErrorCode::$__names[$e->errorCode].": ".$e->parameter;
            }
            else
            {
                $result['message'] = 'Error listing notebooks: '.$e->getCode().": ".$e->getMessage();
            }
        }
        catch (\edam_error_EDAMUserException $e)
        {
            $result['status'] = false;

            if (isset(\edam_error_EDAMErrorCode::$__names[$e->errorCode]))
            {
                $result['message'] = 'Error listing notebooks: '.\edam_error_EDAMErrorCode::$__names[$e->errorCode].": ".$e->parameter;
            }
            else
            {
                $result['message'] = 'Error listing notebooks: '.$e->getCode().": ".$e->getMessage();
            }
        }
        catch (\edam_error_EDAMNotFoundException $e)
        {
            $result['status'] = false;

            if (isset(\edam_error_EDAMErrorCode::$__names[$e->errorCode]))
            {
                $result['message'] = 'Error listing notebooks: '.\edam_error_EDAMErrorCode::$__names[$e->errorCode].": ".$e->parameter;
            }
            else
            {
                $result['message'] = 'Error listing notebooks: '.$e->getCode().": ".$e->getMessage();
            }
        }
        catch (\Exception $e)
        {
            $result['status'] = false;
            $result['message'] = 'Error listing notebooks: '.$e->getMessage();
        }

        return $result;
    }

    public function find_notes($filter)
    {
        $result = array();

        try
        {
            $note_store = $this->note_store();

            $access_token = $this->access_token;
            $max_notes = 1000;

            $find = function($offset) use(&$find, $note_store, $access_token, $filter, $max_notes)
            {
                $result = array();
                $notes = $note_store->findNotes($access_token, $filter, $offset, $max_notes);

                if (!empty($notes) && !empty($notes->notes))
                {
                    foreach ($notes->notes as $note)
                    {
                        $result[] = $note;
                    }

                    if (count($notes->notes) === $max_notes)
                    {
                        array_merge($result, $find($offset + $max_notes));
                    }
                }

                return $result;
            };

            $result['result'] = $find(0);
            $result['status'] = true;
        }
        catch (\edam_error_EDAMSystemException $e)
        {
            $result['status'] = false;

            if (isset(\edam_error_EDAMErrorCode::$__names[$e->errorCode]))
            {
                $result['message'] = 'Error finding notes: '.\edam_error_EDAMErrorCode::$__names[$e->errorCode].": ".$e->parameter;
            }
            else
            {
                $result['message'] = 'Error finding notes: '.$e->getCode().": ".$e->getMessage();
            }
        }
        catch (\edam_error_EDAMUserException $e)
        {
            $result['status'] = false;

            if (isset(\edam_error_EDAMErrorCode::$__names[$e->errorCode]))
            {
                $result['message'] = 'Error finding notes: '.\edam_error_EDAMErrorCode::$__names[$e->errorCode].": ".$e->parameter;
            }
            else
            {
                $result['message'] = 'Error finding notes: '.$e->getCode().": ".$e->getMessage();
            }
        }
        catch (\edam_error_EDAMNotFoundException $e)
        {
            $result['status'] = false;

            if (isset(\edam_error_EDAMErrorCode::$__names[$e->errorCode]))
            {
                $result['message'] = 'Error finding notes: '.\edam_error_EDAMErrorCode::$__names[$e->errorCode].": ".$e->parameter;
            }
            else
            {
                $result['message'] = 'Error finding notes: '.$e->getCode().": ".$e->getMessage();
            }
        }
        catch (\Exception $e)
        {
            $result['status'] = false;
            $result['message'] = 'Error finding notes: '.$e->getMessage();
        }

        return $result;
    }
}
