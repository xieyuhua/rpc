<?php
use Swoole\Server;

class JsonRpcServer {
    private $server;
    private $handlers = [];
    private $config = [
        'host' => '0.0.0.0',
        'port' => 6023,
        'worker_num' => 4,
        'package_max_length' => 2065853043   // 限制单包最大
    ];

    public function __construct(array $config = []) {
        $this->config = array_merge($this->config, $config);
        $this->server = new Server(
            $this->config['host'], 
            $this->config['port'],
            SWOOLE_PROCESS,
            SWOOLE_SOCK_TCP
        );
        $this->configureProtocol();
    }

    // 配置协议参数
    private function configureProtocol() : void {
        $this->server->set([
            'worker_num' => $this->config['worker_num'],
            'open_length_check'     => false,
            'package_length_type'    => 'N',  // 4字节大端序长度头:ml-citation{ref="4,8" data="citationList"}
            'package_length_offset'  => 0,
            'package_body_offset'    => 4,
            'package_max_length'     => $this->config['package_max_length']
        ]);
    }

    // 注册RPC方法
    public function register(string $method, callable $handler) : void {
        $this->handlers[$method] = $handler;
    }

    // 启动服务
    public function start() : void {
        echo 'start '.$this->config['host'].':'.$this->config['port'].PHP_EOL;
        $this->server->on('receive', function ($serv, $fd, $reactorId, $data) {
            print_r($data).PHP_EOL;
            try {
                // // 解析协议头
                // $header = substr($data, 0, 4);
                // $length = unpack('N', $header)[1];
                // $data = substr($data, 4, $length);
                // $request = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                $request = json_decode($data, true);
                // 校验JSON-RPC 2.0规范
                if (!isset($request['jsonrpc']) || $request['jsonrpc'] !== '2.0') {
                    throw new RuntimeException('Invalid JSON-RPC version', -32600);
                }
                
                // 执行方法
                if (!isset($this->handlers[$request['method']])) {
                    throw new RuntimeException('Method not found', -32601);
                }
                $result = $this->handlers[$request['method']]($request['params'] ?? []);

                $response = [
                    'jsonrpc' => '2.0',
                    'result'  => $result,
                    'id'      => $request['id'] ?? null
                ];
            } catch (Throwable $e) {
                $response = [
                    'jsonrpc' => '2.0',
                    'error'   => [
                        'code'    => $e->getCode(),
                        'message' => $e->getMessage()
                    ],
                    'id' => $request['id'] ?? null
                ];
            }

            // 发送响应（添加长度头）
            $responseData = json_encode($response, JSON_UNESCAPED_UNICODE);
            // $serv->send($fd, pack('N', strlen($responseData)) . $responseData);
            $serv->send($fd,  $responseData);
        });

        $this->server->start();
    }
}


// 启动服务端
$server = new JsonRpcServer(['port' => 6023]);

$server->register('add', function ($params) {
    if (!is_array($params) || count($params) != 2) {
        throw new InvalidArgumentException('参数必须为两个数字', -32602);
    }
    return $params[0] + $params[1];
});
$server->start();

