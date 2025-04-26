# 服务端
# 导入 grpc 第三方库
import grpc
# 导入自动生成的两个 py 文件, 还是那句话, matsuri_pb2 是给 protobuf 用的, matsuri_pb2_grpc 是给 grpc 用的
# 这两个文件的名字比较类似, 容易搞混
# import matsuri_pb2 as pb2
# import matsuri_pb2_grpc as pb2_grpc
from proto import matsuri_pb2 as pb2
from proto import matsuri_pb2_grpc as pb2_grpc

# 我们在 protobuf 里面创建的服务叫 Matsuri, 所以 pb2_grpc 会给我们提供一个名为 MatsuriServicer 的类
# 我们直接继承它即可, 当然我们这里的类名叫什么就无所谓了
class Matsuri(pb2_grpc.MatsuriServicer):

    # 我们定义的服务里面有一个 hello_matsuri 的函数
    def hello_matsuri(self, matsuri_request, context):
        """
        matsuri_request 就是相应的参数(载体): name、age都在里面
        当然我们也可以不叫 matsuri_request, 直接叫 request 也是可以的, 它只是一个变量名
        :param request:
        :param context:
        :return:
        """
        name = matsuri_request.name
        age = matsuri_request.age

        # 里面返回是 matsuri_response, 注意: 必须是这个名字, 因为我们在 protobuf 文件中定义的就是 matsuri_response
        # 这个 matsuri_response 内部只有一个字符串类型的 result, result 需要放在 matsuri_response 里面
        return pb2.matsuri_response(result=f"name is {name}, {age} years old")


if __name__ == '__main__':
    # 创建一个 gRPC 服务
    # 里面传入一个线程池, 我们这里就启动 4 个线程吧
    from concurrent.futures import ThreadPoolExecutor
    grpc_server = grpc.server(ThreadPoolExecutor(max_workers=4))
    # 将我们定义的类的实例对象注册到 gRPC 服务中, 我们看到这些方法的名字都是基于我们定义 protobuf 文件
    pb2_grpc.add_MatsuriServicer_to_server(Matsuri(), grpc_server)
    # 绑定ip和端口
    grpc_server.add_insecure_port("127.0.0.1:22222")
    # 启动服务
    grpc_server.start()

    # 注意: 如果直接这么启动的话, 会发现程序启动之后就会立刻停止
    # 因为里面的线程应该是守护线程, 主线程一结束服务就没了
    # 所以我们还需要调用一个 wait_fort_termination
    grpc_server.wait_for_termination()

