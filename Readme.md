# Fake API

##### Simple application for simulating real APIs.

Lets say you need to connect your application on your local/development environment to a an existing api or maybe your partner api.
However they do not have a test or sandbox environment for testing and/or development. 


Just define the endpoint you need to call in a simple json config file
```json
{
    "/example/*": {
        "request": {
            "methods" : ["GET"]
        },
        "response": {
            "statusCode": 200,
            "Content-Type": "application/json",
            "headers": {
                "X-header-name": "header value"
            },
            "body": {
                "data": {
                    "wildcardValue": "[[[$1]]]"
                }
            }
        }
    }
}
```
put it into `./endpoints` and run the application (the application will attempt to load all files in the `endpoints` directory and nested directories). When calling the endpoint `http://yourapphost/example/69` the response will be:
```http request
HTTP/1.1 200 OK
Date: Sat, 01 Jan 2020 00:00:00 GMT
Server: Apache/2.4.38 (Debian)
X-Powered-By: PHP/7.4.5
X-header-name: header value
Content-Length: 31
Keep-Alive: timeout=5, max=100
Connection: Keep-Alive
Content-Type: application/json

{"data":{"wildcardValue":"69"}}
```
As shown in the example you can also define endpoints with wildcard and accss them dynamically in the response body.
The request will also be logged in the `logs` directory
```bash
./logs
├── ⁄example⁄🞯
│   └── 2000-01-01_00-00-00
└── .gitignore
```
in a folder named samed as the defined endpoint path, only with the symbols `/` and `*` replaced with `⁄` and `🞯`). You can change these symbols by passing `['LOGS_SLASH' => '_']` and `['LOGS_STAR' => 'W']` into the FakeApp constructor or setting environmental variables with the same names.

Example of a logged request:
```http request
GET /example/69?queryparam=queryparamvalue HTTP/1.1
Content-Type: text/plain
User-Agent: PostmanRuntime/7.25.0
Accept: */*
Cache-Control: no-cache
Postman-Token: d81827bb-4e7c-47ae-a87e-c5b15790d72e
Host: 192.168.2.253:8000
Accept-Encoding: gzip, deflate, br
Connection: keep-alive
Content-Length: 9

test body
```
For now the application only supports json requests and responses, I'll add more when I feel like it.
Installation is pretty simple, example commands to start the application:
```bash
docker build -t appname_fake_api .
docker run -d \
    --name appname_fake_api \
    -p 6969:80 \
    -v /mnt/fakeapi/endpoints:/var/www/html/endpoints \
    -v /var/log/appnamefakeapi:/var/www/html/logs \
    appname_fake_api:latest
```