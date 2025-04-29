<?php
class JsonRpcClient {
    private $socket;
    private $requestId = 0;
    private $host;
    private $port;
    private $timeout;
    // 构造方法
    public function __construct(string $host, int $port, int $timeout = 3) {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->connect();
    }

    private function connect() {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new RuntimeException("套接字创建失败: " . socket_strerror(socket_last_error()));
        }
        // 超时连接
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        if (!socket_connect($this->socket, $this->host, $this->port)) {
            $errorCode = socket_last_error($this->socket);
            $errorMsg = socket_strerror($errorCode);
            socket_close($this->socket);
            throw new RuntimeException("连接失败 [$errorCode]: $errorMsg");
        }
    }

    private function ensureConnection() {
        if (!is_resource($this->socket) || socket_last_error($this->socket) !== 0) {
            $this->connect();
        }
    }

    public function call(string $method, array $params) {
        $this->ensureConnection();
        //发送数据
        $request = json_encode([
            "jsonrpc" => "2.0",
            "method" => $method,
            "params" => $params,
            "id" => ++$this->requestId
        ], JSON_UNESCAPED_UNICODE) . "\n";

        $retry = 0;
        while ($retry < 2) {
            try {
              //发送请求
                $bytesSent = socket_write($this->socket, $request, strlen($request));
                if ($bytesSent === false || $bytesSent < strlen($request)) {
                    throw new RuntimeException("写入请求失败: " . socket_strerror(socket_last_error($this->socket)));
                }
              //解析响应
                return $this->parseResponse();
            } catch (RuntimeException $e) {
                if ($retry >= 1) throw $e;
                $this->connect();
                $retry++;
            }
        }
    }

    private function parseResponse() {
        // 读取响应
        $response = '';
        $startTime = time();
        while (true) {
            if (time() - $startTime > 5) {
                throw new RuntimeException("响应超时");
            }
            $chunk = '';
            // socket_recv
            $bytesReceived = socket_recv($this->socket, $chunk, 4096, MSG_DONTWAIT);
            if ($bytesReceived === false) {
                $errorCode = socket_last_error($this->socket);
                if ($errorCode === SOCKET_EWOULDBLOCK) {
                    usleep(100000);
                    continue;
                }
                throw new RuntimeException("接收错误: " . socket_strerror($errorCode));
            } elseif ($bytesReceived === 0) {
                break;  // 连接关闭
            }
            $response .= $chunk;
            if (json_decode($response) !== null) {
                break;  // 完整 JSON 终止
            }
        }
        // json_decode
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("无效的JSON响应: " . json_last_error_msg());
        }
        if (isset($decoded['error'])) {
            throw new RuntimeException(
                $decoded['error']['message'] ?? 'Unknown error',
                $decoded['error']['code'] ?? 0
            );
        }
        return $decoded['result'] ?? null;
    }
  
    public function __destruct() {
        if (is_resource($this->socket)) {
            socket_close($this->socket);
        }
    }
}


// 使用示例
try {
    $client = new JsonRpcClient('127.0.0.1', 6023);
    $user = $client->call('add', [1001,456]);
    print_r($user); // 输出 
} catch (Throwable $e) {
    echo "RPC 调用失败: {$e->getMessage()}";
}
