"""
问一句，我们可以使用 requests 进行发送请求吗？
思考一下就知道是不可以的，因为 requests 发送的请求采用的是 HTTP 协议
发送出去的都是 HTTP 协议的文本，当然本质上也是一个字符串，由 header + 请求内容 组成

但是并不代表我们就不能使用 requests 发送，首先使用 requests 是可以连接到 Go 编写的服务端的
因为 HTTP 连接也是基于 TCP 连接的，只不过 Go 的 tcp 服务只负责解析请求的内容
而 requests 发送的数据除了请求内容之外还有很多的 header
但 Go 的 tcp 服务不负责解析这些 header，它只解析请求内容，所以此时采用 requests 是不行的
并不是它不能够发送请求
"""
from pprint import pprint
import asyncio
import simplejson as json

# 所以下面我们使用 asyncio 来进行模拟，当然你也可以使用 socket
async def f(name: str):
    # 建立 tcp 连接
    reader, writer = await asyncio.open_connection(
        "localhost", 6023)  # type: asyncio.StreamReader, asyncio.StreamWriter
    # 创建请求体，并且需要编码成字节
    payload = json.dumps({"method": "Service.Hello", "params": [name], "id": 0}).encode("utf-8")
    # 发送数据
    writer.write(payload)
    await writer.drain()
    # 读取数据
    data = await reader.readuntil(b"\n")
    writer.close()
    return json.loads(data)


async def main():
    name_lst = ["古明地觉", "古明地恋", "雾雨魔理沙", "琪露诺", "芙兰朵露"]
    loop = asyncio.get_running_loop()
    task = [loop.create_task(f(name)) for name in name_lst]
    result = await asyncio.gather(*task)
    return result


res = asyncio.run(main())
pprint(res)
"""
[{'error': None, 'id': 0, 'result': 'hello 古明地觉'},
 {'error': None, 'id': 0, 'result': 'hello 古明地恋'},
 {'error': None, 'id': 0, 'result': 'hello 雾雨魔理沙'},
 {'error': None, 'id': 0, 'result': 'hello 琪露诺'},
 {'error': None, 'id': 0, 'result': 'hello 芙兰朵露'}]
"""
