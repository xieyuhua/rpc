<?php
class JsonRpcClient {
    private $socket;
    private $requestId = 0;
    private $host;
    private $port;
    private $timeout;

    // 增加可配置参数
    public function __construct(string $host, int $port, int $timeout = 3) {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->connect();
    }

    // 分离连接逻辑
    private function connect() {
        $this->socket = @fsockopen(
            $this->host, 
            $this->port, 
            $errno, 
            $errstr, 
            $this->timeout
        );
        
        if (!$this->socket) {
            throw new RuntimeException("连接失败 [$errno]: $errstr");
        }
        
        // 设置流超时参数
        stream_set_timeout($this->socket, $this->timeout);
    }

    // 增加连接状态检查
    private function ensureConnection() {
        if (!is_resource($this->socket) || feof($this->socket)) {
            $this->connect();
        }
    }

    public function call(string $method, array $params) {
        $this->ensureConnection();

        // 构建规范请求
        $request = json_encode([
            "jsonrpc" => "2.0",
            "method"  => $method,
            "params"  => $params,
            "id"      => ++$this->requestId
        ], JSON_UNESCAPED_UNICODE) . "\n";  // 添加换行符作为消息边界

        // 重试机制
        $retry = 0;
        while ($retry < 2) {
            try {
                if (fwrite($this->socket, $request) === false) {
                    throw new RuntimeException(message: "写入请求失败");
                }

                return $this->parseResponse();
            } catch (RuntimeException $e) {
                if ($retry >= 1) throw $e;
                $this->connect();
                $retry++;
            }
        }
    }

    // 分离响应处理逻辑
    private function parseResponse() {
        $response = '';
        while (!feof($this->socket)) {
            $buffer = fgets($this->socket);
            if ($buffer === false) break;
            $response .= $buffer;
            if (strpos($buffer, "\n") !== false) break;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("无效的JSON响应: " . json_last_error_msg());
        }

        // 错误处理标准化
        if (isset($decoded['error'])) {
            throw new RuntimeException(
                $decoded['error']['message'] ?? 'Unknown error',
                $decoded['error']['code'] ?? 0
            );
        }
        //call  result
        return $decoded['result'] ?? null;
    }

    // 增加关闭连接的析构方法
    public function __destruct() {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }
}

// 使用示例
try {
    $client = new JsonRpcClient('192.168.5.254', 6023, 5);
    $result = $client->call('Service.Hello', ["古明地恋","古明地恋"]);
    print_r($result);
} catch (RuntimeException $e) {
    echo "RPC错误: [{$e->getCode()}] {$e->getMessage()}";
} catch (Exception $e) {
    echo "系统错误: [{$e->getCode()}] {$e->getMessage()}";
}
