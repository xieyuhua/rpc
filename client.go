package main

import (
    "fmt"
    "net"
    "net/rpc"
    "net/rpc/jsonrpc"
)

func main() {
    // 这里需要使用 net.Dial 进行连接，之前使用 rpc 是因为需要采用 Gob 协议，但是现在我们不需要了
    // 而 net.Dial 的返回结果是连接，就不再是客户端了，当然我们后面要根据这个连接来创建客户端
    conn, err := net.Dial("tcp", "localhost:9999")
    if err != nil {
        fmt.Println("连接建立失败，失败原因:", err)
        return
    }
    var reply string
    // 服务端是 NewServerCodec，客户端是 NewClientCodec 来进行数据的编解码
    client := rpc.NewClientWithCodec(jsonrpc.NewClientCodec(conn))
    // 然后调用的方式不变，但是序列化之后的数据变了，因为不是同一种协议
    if err := client.Call("Hello Service.Hello", "古明地觉", &reply); err != nil {
        fmt.Println("调用失败，失败原因:", err)
        return
    }
    fmt.Println(reply)  // hello 古明地觉
}
