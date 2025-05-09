package main

import (
	"fmt"
	"github.com/spiral/goridge/v2"
	"log"
	"net"
	"net/rpc"
    "github.com/sirupsen/logrus"
	"time"
	"strings"
	"database/sql"
	_ "github.com/ClickHouse/clickhouse-go"
	_ "github.com/denisenkom/go-mssqldb"
    _ "github.com/go-sql-driver/mysql"
	oracle "github.com/wdrabbit/gorm-oracle"
	"gorm.io/gorm"
	"flag"
	"gopkg.in/natefinch/lumberjack.v2"
	"encoding/json"
)


var allowip *string
var port *string
var dns *string
var dbtype *string
var logs = logrus.New()
var err error
var db *sql.DB
var dbh2 *gorm.DB
var debug *bool

func init (){
    debug   = flag.Bool("d", true, "debug msg")
    port    = flag.String("p", "6001", "服务端口")
    dbtype  = flag.String("t", "mysql", "mysql、sqlserver、oracle、clickhouse")
    dns     = flag.String("dns", "root:root@tcp(192.167.1.6:3307)/xieyuhua", "ClickHouse   tcp://127.0.0.1:42722?debug=false&database=azom_db&write_timeout=5&compress=true&username=default&password=password \nsqlserver    sqlserver://kangshu:bzynj@127.0.0.1:1433/?database=weixin&encrypt=disable \nMysql        root:root@tcp(127.0.0.1:3306)/test \nOracle       oracle://H2:hysoft@127.0.0.1:3521/hyee")
    flag.Parse()
}

// App sample
type App struct{}

// Hi returns greeting message.
func (a *App) Select(sql_str string, r *string ) error {
    
	//记录日志
    logs.WithFields(logrus.Fields{
        "db": *dbtype,
    }).Info(sql_str)
    
    //当前连接是否需要重新连接
    errors  := db.Ping()
    if errors != nil {
        if *dbtype == "oracle" {
            //数据库连接
        	dbh2, _ = gorm.Open(oracle.Open(*dns), &gorm.Config{})
            db, _ = dbh2.DB()
            db.SetMaxOpenConns(50)
            db.SetMaxIdleConns(10)
            db.SetConnMaxLifetime(1800 * time.Second)
        } else {
            //数据库连接
        	db, _ = sql.Open(*dbtype, *dns)
        	db.SetMaxOpenConns(50)
        	db.SetMaxIdleConns(10)
        	db.SetConnMaxIdleTime(1800 * time.Second)
        }  
    }
    
    //数据查询
    var rows *sql.Rows
    resutData := make([](map[string]interface{}), 0)
    if *dbtype=="oracle"{
    	rows, err = dbh2.Raw(sql_str).Rows()
    	if err != nil {
    		panic(err)
    	}
    	
    	cols, _ := rows.Columns()
    	colsize := len(cols)
    	for rows.Next() {
    		colsjson := make(map[string]interface{}, colsize)
    		colmeta := make([]interface{}, colsize)
    		for i := 0; i < colsize; i++ {
    			colmeta[i] = new(interface{})
    		}
    		rows.Scan(colmeta...)
    		for i := 0; i < colsize; i++ {
    			v := colmeta[i].(*interface{})
    			var c string
    			switch (*v).(type) {
    			case nil:
    				c = ""
    			case float64, float32:
    				c = fmt.Sprintf("%v", *v)
    			case int64, int32, int16:
    				c = fmt.Sprintf("%v", *v)
    			default:
    				c = fmt.Sprintf("%s", *v)
    			}
    			colsjson[strings.ToLower(cols[i])] = c
    		}
    		resutData = append(resutData, colsjson)
    	}
    }else{
		rows, err = db.Query(sql_str)
		if err != nil {
			panic(err)
		}
		
	    cols, _ := rows.Columns()
    	colsize := len(cols)
    	for rows.Next() {
    		colsjson := make(map[string]interface{}, colsize)
    		colmeta  := make([]interface{}, colsize)
    		for i := 0; i < colsize; i++ {
    			colmeta[i] = new(interface{})
    		}
    		rows.Scan(colmeta...)
    		for i := 0; i < colsize; i++ {
    			v := colmeta[i].(*interface{})
    			var c string
    			switch (*v).(type) {
    			case nil:
    				c = ""
    			case float64, float32:
    				c = fmt.Sprintf("%v", *v)
    			case int64, int32, int16:
    				c = fmt.Sprintf("%v", *v)
    			default:
    				c = fmt.Sprintf("%s", *v)
    			}
    			colsjson[strings.ToLower(cols[i])] = c
    		}
    		resutData = append(resutData, colsjson)
    	}
    }
	
	defer rows.Close()      
    
    jsonBytes, err := json.Marshal(resutData)
    if err != nil {
        fmt.Println("转换失败:", err)
        return nil
    }
    
    *r = string(jsonBytes)  
	return nil
}


func main() {
	ln, err := net.Listen("tcp", fmt.Sprintf(":%v", *port))
	if err != nil {
		panic(err)
	}

	err = rpc.Register(new(App))
	if err != nil {
		panic(err)
	}
	log.Printf("started")

    // oracle,mysql,sqlsver
	//初始化
    initDb()
    initLog()
    //Ping  10
    go func(){
    	for range time.Tick(time.Duration(10) * time.Second) {
    	    _ = db.Ping()
	    }
    }()
    
    // Accept
	for {
		conn, err := ln.Accept()
		if err != nil {
			continue
		}
		log.Printf("new connection %+v", conn)
		go rpc.ServeCodec(goridge.NewCodec(conn))
	}
}

func initLog (){
    //日志
    logs.SetFormatter(&logrus.JSONFormatter{})
    
	logger := &lumberjack.Logger{
		Filename:   "logrus.log",
		MaxSize:    50,  // 日志文件大小，单位是 MB
		MaxBackups: 3,    // 最大过期日志保留个数
		MaxAge:     30,   // 保留过期文件最大时间，单位 天
		Compress:   true, // 是否压缩日志，默认是不压缩。这里设置为true，压缩日志
	}
	logs.SetOutput(logger) // logrus 设置日志的输出方式
}

func initDb (){
	//oracle://H2:hydeesoft@127.0.0.1:3521/hydee 
	//root:ef08ef776ce21a44@tcp(127.0.0.1:3306)/after
	//sqlserver://kangshu:bzdmmynj@127.0.0.1:1433/?database=weixin&encrypt=disable
	//tcp://127.0.0.1:42722?debug=false&database=azmbk_com_db&write_timeout=5&compress=true&username=default&password=xieyuhua
	//127.0.0.1:9200
    if *dbtype == "oracle" {
        //数据库连接
    	dbh2, err = gorm.Open(oracle.Open(*dns), &gorm.Config{})
    	if err != nil {
    		panic(err)
    	}
        db, err = dbh2.DB()
    	if err != nil {
    		panic(err)
    	}
        db.SetMaxOpenConns(50)
        db.SetMaxIdleConns(10)
        db.SetConnMaxLifetime(1800 * time.Second)
        // defer sqlDB.Close()
    } else {
        //数据库连接
    	db, err = sql.Open(*dbtype, *dns)
    	if err != nil {
    		panic(err)
    	}
    	db.SetMaxOpenConns(50)//   设置连接数总数, 需要根据实际业务来测算, 应小于 mysql.max_connection (应该远远小于), 后续根据指标进行调整
    	db.SetMaxIdleConns(10)//  设置最大空闲连接数, 该数值应该小于等于 SetMaxOpenConns 设置的值
    // 	db.SetConnMaxLifetime(8600)// 设置连接最大生命周期, 默认为 0(不限制), 我不建议设置该值, 只有当 mysql 服务器出现问题, 会导致连接报错, 恢复后可以自动恢复正常, 而我们配置了时间也不能卡住出问题的时间, 配置小还不如使用 SetConnMaxIdleTime 来解决
    	db.SetConnMaxIdleTime(1800 * time.Second) // 设置空闲状态最大生命周期, 该值应小于 mysql.wait_timeout 的值, 以避免被服务端断开连接, 产生报错影响业务， 一般可以配置 1天。
    }
}
