# 客户端
import grpc
# import matsuri_pb2 as pb2
# import matsuri_pb2_grpc as pb2_grpc
from proto import matsuri_pb2 as pb2
from proto import matsuri_pb2_grpc as pb2_grpc

# 定义一个频道, 连接至服务端监听的端口
channel = grpc.insecure_channel("127.0.0.1:22222")
# 生成客户端存根 
client = pb2_grpc.MatsuriStub(channel=channel)

# 然后我们就可以直接调用 Matsuri 服务里面的函数了
print("准备使用服务了~~~~")
while True:
    name, age = input("请输入姓名和年龄, 并使用逗号分割:").split(",")
    # 调用函数, 传入参数 matsuri_request, name 和 age 位于 matsuri_request 中; 因为不能直接发送, 需要序列化成 protobuf
    # 注意: 必须是 matsuri_request, 因为我们在 protobuf 文件定义的就是 matsuri_request
    matsuri_response = client.hello_matsuri(
        pb2.matsuri_request(name=name, age=int(age))
    )
    # result 位于返回值 matsuri_response 中, 直接通过属性访问的形式获取
    # 而之所以能够这么做, 也是客户端存根在背后为我们完成的, 当然这里也可以不叫 matsuri_response, 它只是一个变量名
    print(matsuri_response.result)
