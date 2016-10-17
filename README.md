# GXNU_MACOPEN_TOOL
- 广西师范大学网页版宽带物理地址开放工具 A web version of Guangxi Normal University broadband MAC OPEN tool.
- 仅支持Linux系统 Only Linux is supported
- 依赖[Workerman](https://github.com/walkor/Workerman)，需要在命令行中运行 Based on [Workerman](https://github.com/walkor/Workerman),run in the command-line interface
- 需要开启SQLite,socket,POSIX,PCNTL extensions for php.
```
git clone https://github.com/hxdyxd/GXNU_MACOPEN_TOOL.git
cd GXNU_MACOPEN_TOOL
git clone https://github.com/walkor/Workerman.git
vim start.php //配置文件
php start.php start -d
```
- EMAIL:HXDYXD@GMAIL.COM 请不要用做商业用途，涉及的MAC传输协议归广西师范大学、桂林电子科技大学网络中心所有。

