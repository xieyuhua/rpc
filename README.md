# rpc
rpc go php test

使用oracle数据库、 rpc客户端和pdo_oci 分别查询122197条数据并且打印  耗时测试
```
#rpc  print
real	0m48.799s
user	0m0.815s
sys 	0m0.807s

#pdo_oci print
real	0m57.076s
user	0m4.731s
sys 	0m1.921s

#rpc 
real	0m28.617s
user	0m0.792s
sys	0m0.286s

#pdo_oci 
real	0m34.471s
user	0m2.444s
sys	0m0.921s
```
