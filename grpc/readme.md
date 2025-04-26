
# https://www.cnblogs.com/traditional/p/9260830.html

# protobuf 还是非常重要的，我们如果想使用 gRPC 服务，就需要先编写一个 protobuf 文件，然后根据这个文件生成对应语言的客户端存根和服务端存根。存根帮我们做好了函数 ID 映射、以及数据序列化反序列化，导入它们即可使用，而我们则只需要专注于业务逻辑即可

>> pip install grpcio grpcio-tools protobuf -i https://pypi.tuna.tsinghua.edu.cn/simp

### 下面我们来编写 protobuf 文件，它有自己的语法格式，所以相比 json 它的门槛比较高。我们的文件名就叫 matsuri.proto，protobuf 文件的后缀是 .proto。

```
// syntax 是指定使用哪一种 protobuf 服务, 现在使用的都是 "proto3"
syntax = "proto3";

// 包名, 这个不是很重要, 你删掉也是无所谓的
package test;

// 编写服务, 每个服务里面有相应的函数(对应 restful 视图函数)
// service 表示创建服务
service Matsuri {
  //使用 rpc 定义函数, 参数名为 matsuri_request, 返回值为 matsuri_response
  rpc hello_matsuri(matsuri_request) returns (matsuri_response){}
}
// 所以我们是创建了一个名为 Matsuri 的服务, 服务里面有一个 hello_matsuri 的函数
// 函数接收一个名为 matsuri_request 的参数, 并返回一个 matsuri_response, 至于结尾的 {} 我们后面再说
// 另外参数 matsuri_request、返回值 matsuri_response 是哪里来的呢? 所以我们还要进行定义

// 注意: matsuri_request 虽然是参数, 但我个人更愿意把它称之为参数的载体
// 比如下面定义两个变量 name 和 age, 客户端会把它们放在 matsuri_request 里面, 在服务端中也会通过 matsuri_request 来获取
message matsuri_request {
  string name = 1; // = 1表示第1个参数
  int32 age = 2;
}

// matsuri_response 同理, 虽然它是返回值, 但我们返回的显然是 result, 只不过需要放在 matsuri_response 里面
// 具体内容在代码中会有体现
message matsuri_response {
  string result = 1;
}
```

# 所以有人可能已经发现了，这个 protobuf 文件就是定义一个服务的框架。然后我们就要用这个 protobuf 文件，来生成对应的 Python 服务端和客户端文件。

>> python -m grpc_tools.protoc --python_out=. --grpc_python_out=. -I. matsuri.proto


# 执行完之后我们看到多出了两个文件，这个是自动帮你生成的，matsuri_pb2.py 是给 protobuf 用的，matsuri_pb2_grpc.py 是给 gRPC 用的。而这两个文件可以用来帮助我们编写服务端和客户端，我们来简单尝试一下，具体细节后面会补充。

>> pip install --upgrade protobuf -i https://pypi.tuna.tsinghua.edu.cn/simple

```
py .\ssgrpc.py
py .\ccgrpc.py
```

所以如果我们要将其放在一个单独的目录中（假设叫 grpc_helper），那么我们应该将 matsuri_pb2_grpc 中的导入逻辑改成这样子：



# 我们来看看采用 protobuf 协议序列化之后的结果是什么，不是说它比较高效吗？那么我们怎能不看看它序列化之后的结果呢，以及它和 json 又有什么不一样呢？

```
import matsuri_pb2 as pb2

request = pb2.matsuri_request(name="koishi", age=15)
# 调用 SerializeToString 方法会得到一个二进制的字符串
print(request.SerializeToString())  # b'\n\x07matsuri\x10\x10'

# 这个字符串显然我们看不懂，我们暂时也不去深究它的意义，总之这就是 protobuf 序列化之后的结果
# 而且我们还可以将其反序列化，不然服务端接收到之后也不认识啊
request2 = pb2.matsuri_request()
request2.ParseFromString(b'\n\x06koishi\x10\x0f')
print(request2.name)  # koishi
print(request2.age)  # 15
"""
是可以正常反序列化的，所以我们不认识没关系，protobuf 认识就行
那么 b'\n\x06koishi\x10\x0f' 到底是啥意思呢？
首先里面的 \x06 表示后面的 6 个字符代表 name 参数的值，而之所以是 name 不是 age
是因为我们在定义 protobuf 文件的时候，name 参数的位置是第 1 个
而 \x0f 就是 16 进制的 15
"""
# 然后来看看 json
import simplejson as json
print(json.dumps({"name": "koishi", "age": 15}).encode("utf-8"))  # b'{"name": "koishi", "age": 15}'

# 可以看到 protobuf 协议序列化之后的结果要比 json 短, 平均能得到一倍的压缩

```










