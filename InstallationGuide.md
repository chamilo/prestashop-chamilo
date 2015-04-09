# Introduction #

Quick installation and configuration guide for the Chamilo plugin inside Prestashop


# Details #

Instructions to install and configure the Prestashop-Chamilo plugin

## Prerequisites ##

  * Prestashop 1.4.0.17 (check sending e-mails work)
  * Chamilo 1.8.8.x and Chamilo 1.9.x
  * Chamilo 1.8.7.1 For this version (and **only** for version 1.8.7.1), you must replace main/inc/webservices/registration.soap.php by the following file: http://classic.chamilo.googlecode.com/hg/main/webservices/registration.soap.php?r=8825fd4b339430381ec3aa7db02aced0a83ad6c2 - Don't forget to take a backup copy first.

### Install the Prestashop - Chamilo module ###

Just follow the steps to install a normal module in Prestashop. Upload the zip file and click on "Install".
See http://addons.prestashop.com/en/content/13-installing-modules for the normal module install procedure.

### Configure the module ###

Click on "Other modules" -> "Chamilo" -> "Configure". You'll see a form with the following parameters to fill:

  * Chamilo URL              : The public URL of your Chamilo portal (with a trailing slash "/")
  * Chamilo Security key     : Alphanumerical value you will find in main/conf/configuration.php in your Chamilo directory
  * Chamilo encrypted method : sha1 or md5. Check the value in main/inc/conf/configuration.php in your Chamilo directory
  * Your public IP           : Public IP of the server where Prestashop is installed. You can check it by issuing a "ping" on the domain name.

Once these parameters are setup, you'll find the list of courses extracted from Chamilo on the same page (you might need to click the "Save" button twice before it appears). This means the module has been configured correctly. Check that your Chamilo portal has existing courses before you think it didn't work.

### Configure a product ###

  1. Login to Prestashop as admin and go to "Catalog" -> "Add new product".
  1. Complete the form and click "Save".
  1. On the same form, click "Parameters". Check these are the parameters of the product and NOT of the system.
  1. A list of parameters appears: Height, Width ... and CHAMILO\_CODE
  1. Place the Chamilo course code right there. This is the way to link a Prestashop product to a Chamilo course.

### Test ###

Make a small test with a client user in Prestashop. Try buying one product that has the CHAMILO\_CODE parameter configured. The Chamilo module will send the e-mails when the order has been confirmed.

### Accept the payment ###

As admin,go to "Orders" and select the order recently created.
Select the "Payment accepted" and click on "Change".
At this point, the module sends the order to Chamilo to create a user account and the subscription of the same user to the course bought.

### End ###

Well done! Now you can sell your Chamilo courses using Prestashop.

## Important to know ##

### Chamilo login ###

The access login for Chamilo is generated using the user's e-mail within Prestashop and adding the customerid. For example, for sammy@example.com, the login in Chamilo will be sammy555 if 555 is the customer ID in Prestashop.

### Chamilo password ###

The password is an auto-generated alphanumerical value sent by e-mail. If you set the option correctly in Chamilo, the user will be able to ask for his password again later through the Chamilo homepage.

### Don't remove the CHAMILO\_CODE parameter in Prestashop ###

If you remove this parameter from Prestashop, the plugin will not be able to link Chamilo courses with a Prestashop product anymore.

### E-mails with access to Chamilo ###

You can modify the e-mail sent to your customers by modifying the /modules/chamilo/mail/[language](your.md) files in your Prestashop directory.

### Buying multiple courses in the same purchase ###

If a client user of Prestashop buys 5 Chamilo courses at the same time in the same order, the module only creates one user account in Chamilo and associates the 5 courses to this account. So the system will only send one e-mail with credentials.

### Buying multiple courses in different orders ###

If a Prestashop customer buys a course and then comes again to buy another course, the system will use the same login created the first time. The customer will not receive another e-mail. We suppose the user already received his access when he bought the first course.
If you set the option correctly in Chamilo, the user will be able to ask for his password again later through the Chamilo homepage.