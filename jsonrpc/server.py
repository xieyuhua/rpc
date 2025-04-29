import socket
import json
import threading
import lz4.frame

# ====================== 路由核心模块 ======================
class Router:
    _routes = {}  # 存储 {方法名: (类名字符串, 方法名称字符串)}

    @classmethod
    def register(cls, method_name, service_class_name):
        def decorator(func):
            # 存储类名字符串和方法名称（非方法对象）
            cls._routes[method_name] = (service_class_name, func.__name__)
            return func
        return decorator

    @classmethod
    def get_method(cls, method_name):
        return cls._routes.get(method_name)

# ====================== 请求处理模块 ======================
def handle_connection(conn):
    buffer = b''
    while True:
        data = conn.recv(1024)
        if not data:
            break
        buffer += data

        # 处理所有完整请求（以换行符分隔）
        while True:
            request_str, sep, remaining = buffer.partition(b'\n')
            if not sep:
                break
            try:
                request = json.loads(request_str)
                response = process_request(request)
                # response = lz4.frame.compress(response)  # 发送前压缩
                conn.sendall(json.dumps(response).encode() + b'\n')
            except Exception as e:
                error_resp = {
                    "jsonrpc": "2.0",
                    "error": {"code": -32603, "message": str(e)},
                    "id": request.get("id") if request else None
                }
                conn.sendall(json.dumps(error_resp).encode() + b'\n')
            buffer = remaining
    conn.close()

# ====================== 业务执行模块 ======================
def process_request(request):
    method = request.get("method")
    params = request.get("params", [])
    request_id = request.get("id")

    # 从路由表获取类名和方法名称
    handler_info = Router.get_method(method)
    if not handler_info:
        raise Exception(f"Method {method} not found")

    # 关键修复点：动态加载类对象
    service_class_name, method_name = handler_info
    service_class = globals()[service_class_name]  # 字符串转类对象
    service_instance = service_class()
    method = getattr(service_instance, method_name)  # 通过名称获取方法
    result = method(params)

    return {
        "jsonrpc": "2.0",
        "result": result,
        "id": request_id
    }

# ====================== 业务服务模块 ======================
class HelloService:
    @Router.register("hello", "HelloService")  # 类名以字符串形式传递
    def hello(self, params):
        return f"hello {params}"

# ====================== 服务器启动模块 ======================
if __name__ == "__main__":
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    # 设置地址复用 避免 Address already in use 错误，允许端口快速释放后重新绑定：
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1) 
    server.bind(("0.0.0.0", 6023))
    server.listen(5)
    # 调整缓冲区大小 根据网络带宽和延迟需求，优化接收/发送缓冲区：
    server.setsockopt(socket.SOL_SOCKET, socket.SO_SNDBUF, 1024 * 1024)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_RCVBUF, 1024 * 1024)
    # 启用非阻塞模式 提升高并发场景处理能力，需配合事件循环（如 select 或 asyncio）：
    # server.setblocking(False) 
    print("JSON-RPC server started on port 6023")
    while True:
        conn, addr = server.accept()
        print(f"New connection from {addr}")
        #‌ 多线程/进程模型  每个连接分配独立线程/进程，避免阻塞主线程：
        threading.Thread(target=handle_connection, args=(conn,)).start()
