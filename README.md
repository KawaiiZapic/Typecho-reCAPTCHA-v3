插件设计的很糟糕, 容易导致Google认为网站正在遭受攻击导致所有验证都不被通过, 请不要使用了.  
代码留作参考.

## Google reCAPTCHA v3 Login/Comment Protect

### 关于
本插件能在登陆后台/评论时进行人机验证,基于`Google reCAPTCHA v3`,实现无交互的人机验证.  
完美兼容绝大部分Pjax换页/Ajax评论主题,无需额外修改.

### 使用方法
1. 下载[本仓库](https://github.com/KawaiiZapic/Typecho-reCAPTCHA-v3/archive/master.zip)
2. 解压,更名文件夹为`GrCv3Protect`
3. 在Typecho后台启用,并填写申请的`siteKey`和`secretKey`
4. 登录保护是自动配置的.  
如果需要对评论启用保护,请在评论表单处添加一行`<?php GrCv3Protect_Plugin::OutputCode(); ?>`.  
某些主题可能存在其他问题(头部不输出/自行接管评论系统/ajax提交表单不完整),请咨询其作者或禁用保护.  
