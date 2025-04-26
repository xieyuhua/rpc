import socket
import json
import threading

class HelloService:
    def hello(self, name):
        return f"hello {name}"

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

def process_request(request):
    method = request.get("method")
    params = request.get("params", [])
    request_id = request.get("id")
    
    # 处理方法路由
    if method == "Service.Hello":
        if isinstance(params, list) and len(params) >= 1:
            name = params[0]
        elif isinstance(params, dict) and "name" in params:
            name = params["name"]
        else:
            raise ValueError("Invalid parameters")
        
        result = HelloService().hello(name)
        return {
            "jsonrpc": "2.0",
            "result": result,
            "id": request_id
        }
    else:
        raise Exception(f"Method {method} not found")

if __name__ == "__main__":
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.bind(("0.0.0.0", 6023))
    server.listen(5)
    print("JSON-RPC server started on port 6023")
    while True:
        conn, addr = server.accept()
        print(f"New connection from {addr}")
        threading.Thread(target=handle_connection, args=(conn,)).start()
