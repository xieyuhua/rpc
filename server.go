package main

import (
    "fmt"
    "net"
    "net/rpc"
    "net/rpc/jsonrpc"
)

type HelloService struct {

}

func (server *HelloService) Hello (request string, reply *string) error {
    *reply = fmt.Sprintf("hello %s", request)
    return nil
}

func main() {
    listener, _ := net.Listen("tcp", ":6023")
    _ = rpc.RegisterName("Service", &HelloService{})
    for {
        conn, _ := listener.Accept()
        // 其它部分不变，这里改成如下
        go rpc.ServeCodec(jsonrpc.NewServerCodec(conn))
    }
}
