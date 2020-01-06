<?php
/**
 *
 *   Copyright © 2010-2012 by xhost.ch GmbH
 *
 *   All rights reserved.
 *
 **/
/**
 * Sample Multicraft API implementation.
 *
 * For examples and function reference, please see:
 * http://www.multicraft.org/site/page?view=api-doc
 *
 **/
class MulticraftApi
{
    private $key = '';
    private $url = '';

    private $lastResponse = '';

    private $methods = array(
            //User functions
            'listUsers'                 => array(),
            'findUsers'                 => array(array('name'=>'field', 'type'=>'array'), array('name'=>'value', 'type'=>'array')),
            'getUser'                   => array('id'),
            'updateUser'                => array('id', array('name'=>'field', 'type'=>'array'), array('name'=>'value', 'type'=>'array'), array('name'=>'send_mail', 'default'=>0)),
            'createUser'                => array('name', 'email', 'password', array('name'=>'lang', 'default'=>''), array('name'=>'send_mail', 'default'=>0)),
            'deleteUser'                => array('id'),
            'getUserRole'               => array('user_id', 'server_id'),
            'setUserRole'               => array('user_id', 'server_id', 'role'),
            'getUserFtpAccess'          => array('user_id', 'server_id'),
            'setUserFtpAccess'          => array('user_id', 'server_id', 'mode'),
            'getUserId'                 => array('name'),
            //Player functions
            'listPlayers'               => array('server_id'),
            'findPlayers'               => array('server_id', array('name'=>'field', 'type'=>'array'), array('name'=>'value', 'type'=>'array')),
            'getPlayer'                 => array('id'),
            'updatePlayer'              => array('id', array('name'=>'field', 'type'=>'array'), array('name'=>'value', 'type'=>'array')),
            'createPlayer'              => array('server_id', 'name'),
            'deletePlayer'              => array('id'),
            'assignPlayerToUser'        => array('player_id', 'user_id'),
            //Command functions
            'listCommands'              => array('server_id'),
            'findCommands'              => array('server_id', array('name'=>'field', 'type'=>'array'), array('name'=>'value', 'type'=>'array')),
            'getCommand'                => array('id'),
            'updateCommand'             => array('id', array('name'=>'field', 'type'=>'array'), array('name'=>'value', 'type'=>'array')),
            'createCommand'             => array('server_id', 'name', 'role', 'chat', 'response', 'run'),
            'deleteCommand'             => array('id'),
            //Server functions
            'listServers'               => array(),
            'findServers'               => array(array('name'=>'field', 'type'=>'array'), array('name'=>'value', 'type'=>'array')),
            'listServersByConnection'   => array('connection_id'),
            'listServersByOwner'        => array('user_id'),
            'getServer'                 => array('id'),
            'updateServer'              => array('id', array('name'=>'field', 'type'=>'array'), array('name'=>'value', 'type'=>'array')),
            'createServerOn'            => array(array('name'=>'daemon_id', 'default'=>0), array('name'=>'no_commands', 'default'=>0), array('name'=>'no_setup_script', 'default'=>0)),
            'createServer'              => array(array('name'=>'name', 'default'=>''), array('name'=>'port', 'default'=>0), array('name'=>'base', 'default'=>''), array('name'=>'players', 'default'=>0), array('name'=>'no_commands', 'default'=>0), array('name'=>'no_setup_script', 'default'=>0)),
            'suspendServer'             => array('id', array('name'=>'stop', 'default'=>1)),
            'resumeServer'              => array('id', array('name'=>'start', 'default'=>1)),
            'deleteServer'              => array('id', array('name'=>'delete_dir', 'default'=>'no')),
            'getServerStatus'           => array('id', array('name'=>'player_list', 'default'=>0)),
            'getServerOwner'            => array('server_id'),
            'setServerOwner'            => array('server_id', 'user_id', array('name'=>'send_mail', 'default'=>0)),
            'getServerConfig'           => array('id'),
            'updateServerConfig'        => array('id', array('name'=>'field', 'type'=>'array'), array('name'=>'value', 'type'=>'array')),
            'startServerBackup'         => array('id'),
            'getServerBackupStatus'     => array('id'),
            'startServer'               => array('id'),
            'stopServer'                => array('id'),
            'restartServer'             => array('id'),
            'startAllServers'           => array(),
            'stopAllServers'            => array(),
            'restartAllServers'         => array(),
            'sendConsoleCommand'        => array('server_id', 'command'),
            'sendAllConsoleCommand'     => array('command'),
            'runCommand'                => array('server_id', 'command_id', array('name'=>'run_for', 'default'=>0)),
            'getServerLog'              => array('id'),
            'clearServerLog'            => array('id'),
            'getServerChat'             => array('id'),
            'clearServerChat'           => array('id'),
            'sendServerControl'         => array('id', 'command'),
            'getServerResources'        => array('id'),
            //Daemon functions
            'listConnections'           => array(),
            'findConnections'           => array(array('name'=>'field', 'type'=>'array'), array('name'=>'value', 'type'=>'array')),
            'getConnection'             => array('id'),
            'removeConnection'          => array('id'),
            'getConnectionStatus'       => array('id'),
            'getConnectionMemory'       => array('id', array('name'=>'include_suspended', 'default'=>0)),
            //Settings functions
            'listSettings'              => array(),
            'getSetting'                => array('key'),
            'setSetting'                => array('key', 'value'),
            'deleteSetting'             => array('key'),
            //Schedule functions
            'listSchedules'             => array('server_id'),
            'findSchedules'             => array('server_id', array('name'=>'field', 'type'=>'array'), array('name'=>'value', 'type'=>'array')),
            'getSchedule'               => array('id'),
            'updateSchedule'            => array('id', array('name'=>'field', 'type'=>'array'), array('name'=>'value', 'type'=>'array')),
            'createSchedule'            => array('server_id', 'name', 'ts', 'interval', 'cmd', 'status', 'for'),
            'deleteSchedule'            => array('id'),
        );

    public function __construct($url, $user, $key)
    {
        $this->url = $url;
        $this->user = $user;
        $this->key = $key;
    }

    public function __call($function, $args)
    {
        $argnames = @$this->methods[$function];
        if (!is_array($argnames))
            return array('success'=>false, 'errors'=>array('Unknown API method "'.$function.'()"'), 'data'=>array());
        $callargs = array();
        $name = ''; $value = '';
        for ($i = 0; $i < count($argnames); $i++)
        {
            if (is_array($argnames[$i]))
                $name = $argnames[$i]['name'];
            else
                $name = $argnames[$i];

            if ($i < count($args))
            {
                $value = $args[$i];
            }
            else if (is_array($argnames[$i]) && isset($argnames[$i]['default']))
            {
                if ($i >= count($args))
                    $value = $argnames[$i]['default'];
                else
                    $value = $args[$i];
            }
            else
                return array('success'=>false, 'errors'=>array('"'.$function.'()": Not enough arguments ('.count($args).')'), 'data'=>array());

            if (is_array($argnames[$i]) && isset($argnames[$i]['type']))
            {
                if ($argnames[$i]['type'] == 'array')
                    $value = json_encode($value);
            }
            $callargs[$name] = $value;
        }
        return $this->call($function, $callargs);
    }


    public function call($method, $params = array())
    {
        if (!$this->url)
            return array('success'=>false, 'errors'=>array('Invalid target URL'));
        if (!$this->key)
            return array('success'=>false, 'errors'=>array('Invalid API key'));
            
        $url = $this->url;
        $query = '?';

        $str = '';
        if (!is_array($params))
            $params = array($params=>$params);
        $params['_MulticraftAPIMethod'] = $method;
        $params['_MulticraftAPIUser'] = $this->user;
        foreach ($params as $p)
            $str .= $p;
        $params['_MulticraftAPIKey'] = md5($this->key.$str);

        foreach ($params as $k=>$v)
            $query .= '&'.urlencode($k).'='.urlencode($v);
        return $this->send($url, $query);
    }

    public function send($url, $query)
    {
        $response = '';
        $error = '';
        if (function_exists('curl_init'))
        {
            $curl = curl_init($url); 
         
            curl_setopt ($curl, CURLOPT_POST, true); 
            curl_setopt ($curl, CURLOPT_POSTFIELDS, $query); 
         
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);   
         
            $response = curl_exec($curl);
            //echo $response;
            $error = curl_error($curl);
            curl_close($curl); 
        }
        else
            $response = file_get_contents($url.$query);

        if (!$response)
        {
            if (!$error)
                $error = 'Empty response (wrong API URL or connection problem)';
            return array('success'=>false, 'errors'=>array($error), 'data'=>'');
        }
        $this->lastResponse = $response;
        $ret = json_decode($response, true);
        if (!is_array($ret))
        {
            return array('success'=>false, 'errors'=>array($ret), 'data'=>array());
        }
        return $ret;
    }

    public function rawResponse()
    {
        return $this->lastResponse;
    }
}
?>