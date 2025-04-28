<?php
// Client.php
class RpcClient {
    private $client;
    private $requestId = 0;

    public function __construct(string $host, int $port) {
        $this->client = new Swoole\Client(SWOOLE_SOCK_TCP);
        if (!$this->client->connect($host, $port, 3)) {
            throw new RuntimeException("连接失败: {$this->client->errCode}");
        }
    }

    public function call(string $method, array $params = []) {
        // 构建 JSON-RPC 请求
        $request = [
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
            'id'      => ++$this->requestId
        ];
        $jsonData = json_encode($request, JSON_UNESCAPED_UNICODE);

        $send = $jsonData;
        print_r($send);
        // 添加长度头并发送
        $this->client->send($send);
        echo PHP_EOL;
        // 接收响应
        $response = $this->client->recv();
        $body = $response;
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            throw new RuntimeException($data['error']['message'], $data['error']['code']);
        }
        return $data['result'];
    }
}

// 使用示例
try {
    $client = new RpcClient('127.0.0.1', 6023);
    $user = $client->call('add', [1001,456]);
    print_r($user); // 输出: ['id' => 1001, 'name' => 'Swoole User', 'age' => 28]
} catch (Throwable $e) {
    echo "RPC 调用失败: {$e->getMessage()}";
}
