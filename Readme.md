# Fake API

##### Simple application for simulating real APIs.

Lets say you need to connect your application on your local/development environment to a an existing api or maybe your partner api.
However they do not have a test or sandbox environment for testing and/or development. 


Just define the endpoint you need to call in a simple json config file
```json
{
    "/example/*": { /* endpoint path */
        "log": true, /* optional, defaults to false */
        "request": { /* optional, defaults to all methods */
            "methods" : ["GET"]
        },
        "responses": {
            "200": { /* response designation */
                "statusCode": 200,
                "json": true, /* optional, defaults to true, adds Content-Type and Content-Length headers */
                "headers": { /* optional */
                    "X-header-name": "header value"
                },
                "body": { /* optional, defaults to '' */
                    "data": {
                        "wildcardValue": "[[[$1]]]" /* wildcard value */
                    }
                }
            },
            "400": {
                "statusCode": 400,
                "body": {
                    "message": "Bad request",
                    "status": 400
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
As shown in the example you can also define endpoints with wildcard and the value passed in the url can be access.
You can define more responses, indexed by a designation. You can choose to return a specific response with passing a query parameter or header with the name "responsecode" and value with the response designation. By default, the first response is chosen.
If you set `"logs": true` in the endpoint config, the request will also be logged in the `logs` directory
```bash
./logs
└── ⁄example⁄🞯
    └── 2000-01-01_00-00-00
```
in a folder named same as the defined endpoint path, only with the symbols `/` and `*` replaced with `⁄` and `🞯`). You can change these symbols by passing `['LOGS_SLASH' => '_']` and `['LOGS_STAR' => '.']` into the FakeApi constructor or setting environmental variables with the same names.

Example of a logged request:
```text

=============
== REQUEST ==
=============
GET /example/1 HTTP/1.1
User-Agent: PostmanRuntime/7.26.8
Accept: */*
Cache-Control: no-cache
Postman-Token: 4805684b-58e4-4978-bc11-9d8545d779c3
Host: 192.168.2.253:8000
Accept-Encoding: gzip, deflate, br
Connection: keep-alive

testbody

==============
== RESPONSE ==
==============
HTTP/1.1 200
X-header-name: header value

{"data":{"wildcardValue":"[[[$1]]]"}}

```
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