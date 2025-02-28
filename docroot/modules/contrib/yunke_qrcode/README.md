Yunke QR Code
===

#overview
> Yunke_QRCode module is used to encode and display the content to QRcode. Its significant feature is that it uses front-end JavaScript to generate QRcode. Compared with some other QRcode modules, it has obvious advantages: QRcode pictures do not need to be downloaded, so save the server performance, and display faster.You can customize the generated QRcode, such as QRcode image size, color, correct level, etc., In use, it pursues minimalism, a few lines of code can produce a QRcode.<br>
>QR code is usually rendered in the form of H5 canvas. In order to be compatible with old browsers, it can also be rendered in the form of table


#Demo
This module is extremely simple to use, after installation, it provides a QRcode render element plugin, only needs a few lines of code to output the QRcode, the example is as follows:

```php
    $renderArr = [
      '#type' => 'yunke_qrcode',
      '#text' => 'Yunke is from Shenzhen, China. He works in Will-Nice(Shenzhen) Technology Co., Ltd',
    ];
    return $renderArr;
```

Just execute the above code in the controller, and as you can see, it's as simple as that,This code will output a QRcode image in the browser with the encoded content as the value of `#text`, you can encode any thing, such as numbers, text, links, etc. This module provides a demo form to demonstrate the full use:

```php
    \Drupal\yunke_qrcode\Form\QRCodeDemoForm
```

Experience it in the controller with the following code:
```php
    $form = \Drupal::formBuilder()->getForm("\Drupal\yunke_qrcode\Form\QRCodeDemoForm");
    return $form;
```

#About the author
This module is developed by Will-Nice (Shenzhen) Technology Co., Ltd<br>
[未来很美](http://www.will-nice.com,"Official Site") http://www.will-nice.com<br>
Developer: Yunke(phpworld@qq.com)<br>
Will-Nice is a dedicated Drupal development company, located in Shenzhen, China, if you have development needs, please contact us

####Related modules
* [qr_codes](https://www.drupal.org/project/qr_codes)
* [google_qr_code](https://www.drupal.org/project/google_qr_code)
* [qr_code_field_formatter](https://www.drupal.org/project/qr_code_field_formatter)
* [QR code field](https://www.drupal.org/project/qrfield)


<br>
<br>

Yunke QR Code
===

#概述
> yunke_qrcode模块用于对内容进行二维码编码并显示，它显著的特点是采用前端javascript来生成二维码，和其他一些二维码模块相比有明显的优点：二维码图片不需要下载，节省了服务器性能，显示也更快。你可以自定义生成二维码的各种属性，比如二维码图片的尺寸、颜色、容错级别等，在使用上追求极致简单，几行代码就能产生一个二维码。<br>
>通常二维码采用H5画布的方式呈现，为了兼容老旧浏览器也可以采用表格方式呈现


#使用示例
该模块的使用极其简单，在安装后，它提供了一个二维码渲染元素类型，只需要几行代码就能输出二维码，示例如下：

```php
    $renderArr = [
      '#type' => 'yunke_qrcode',
      '#text' => '云客来自中国深圳，供职于未来很美(深圳）科技有限公司',
    ];
    return $renderArr;
```

在控制器中执行以上代码即可，如你所见，一切就是这么简单，以上代码会在浏览器中输出一张二维码图片，编码内容即是`#text`的值，你可以编码任意值，如数字、文本、链接等，本模块提供了一个示例表单来演示完整使用：

```php
    \Drupal\yunke_qrcode\Form\QRCodeDemoForm
```

在控制器中用以下代码可以体验它：
```php
    $form = \Drupal::formBuilder()->getForm("\Drupal\yunke_qrcode\Form\QRCodeDemoForm");
    return $form;
```

#关于作者
本模块由“未来很美（深圳）科技有限公司”开发<br>
[未来很美](http://www.will-nice.com,"官方网站") http://www.will-nice.com<br>
开发者：云客（phpworld@qq.com)<br>
未来很美科技是一家专注于Drupal开发的公司，位于中国深圳，如有开发需求欢迎联系

####类似模块
* [qr_codes](https://www.drupal.org/project/qr_codes)
* [google_qr_code](https://www.drupal.org/project/google_qr_code)
* [qr_code_field_formatter](https://www.drupal.org/project/qr_code_field_formatter)
* [QR code field](https://www.drupal.org/project/qrfield)


