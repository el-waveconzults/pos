# SMTP Setup Guide for Welcome Emails

This guide explains how to configure SMTP for automatic welcome emails after sign-up.

## Overview

Your POS system now supports automated welcome emails sent to both the company and admin user after registration. Emails can be sent via:

1. **SMTP** (Recommended) - Reliable, professional email sending
2. **PHP mail()** - Uses server's built-in mail function (fallback)

---

## Option 1: Setup SMTP (Recommended)

### Step 1: Get Your SMTP Credentials

Choose your email provider and get the SMTP details:

#### **Gmail** (Free, requires app password)

- **Host:** `smtp.gmail.com`
- **Port:** `587`
- **Encryption:** `tls`
- **Username:** Your Gmail address (e.g., `yourname@gmail.com`)
- **Password:** Your Gmail app password (not your regular password)

**Important:** Gmail requires an "App Password" for SMTP access:

1. Enable 2-Factor Authentication on your Google account
2. Go to [Google Account Settings](https://myaccount.google.com/security)
3. Select "App passwords" under "Signing in to Google"
4. Generate a new app password for "Mail"
5. Use this 16-character password in the MAIL_PASSWORD field

#### **Namecheap Private Email** (Recommended)

- **Host:** `mail.privateemail.com`
- **Port:** `587` (recommended) or `465` (SSL)
- **Encryption:** `tls` (for port 587) or `ssl` (for port 465)
- **Username:** Your full email address (e.g., `yourname@yourdomain.com`)
- **Password:** Your email account password

**Note:** Namecheap Private Email uses the same SMTP settings as most cPanel-based email hosting.

#### **Office 365/Outlook**

- **Host:** `smtp.office365.com`
- **Port:** `587`
- **Encryption:** `tls`
- **Username:** Your email address
- **Password:** Your Outlook password

#### **SendGrid**

- **Host:** `smtp.sendgrid.net`
- **Port:** `587`
- **Encryption:** `tls`
- **Username:** `apikey`
- **Password:** Your SendGrid API key

#### **Other Services**

Consult your email provider's SMTP documentation for their specific settings.

---

### Step 2: Configure .env File

1. Copy `.env.example` to `.env` in the root directory:

   ```bash
   cp .env.example .env
   ```

2. Edit `.env` and fill in your SMTP settings:

   ```ini
   MAIL_DRIVER=smtp
   MAIL_HOST=mail.privateemail.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@yourdomain.com
   MAIL_PASSWORD=your-email-password
   MAIL_FROM_ADDRESS=your-email@yourdomain.com
   MAIL_FROM_NAME=Your POS System Name
   MAIL_ENCRYPTION=tls
   ```

3. Save the file

---

### Step 3: Test Your Configuration

1. Register a new company
2. Check if the welcome emails arrive in the inbox
3. If emails don't arrive, check:
   - `.env` file has correct credentials
   - Firewall isn't blocking port 587
   - Email provider allows external connections
   - Check server logs: `logs/error.log`

---

## Option 2: Use PHP mail() (Fallback)

If SMTP setup is too complex, you can use PHP's built-in `mail()` function:

1. Set `MAIL_DRIVER=mail` in `.env`:

   ```ini
   MAIL_DRIVER=mail
   ```

2. Make sure your server has mail configured (talk to your hosting provider)

**Note:** PHP mail() is less reliable and may have deliverability issues.

---

## Email Features

### Welcome Emails Sent On Registration:

1. **Admin Welcome Email**
   - Sent to: Admin's email address
   - Contains: Account details, getting started guide, login link
   - Subject: "Welcome to [Your POS System]"

2. **Company Welcome Email**
   - Sent to: Company's email address
   - Contains: Company registration confirmation, login link
   - Subject: "[Your POS System] - Company Registration Confirmation"

---

## Troubleshooting

### Emails Not Sending?

1. **Check SMTP Configuration**

   ```bash
   # Look for email errors in the log
   tail logs/error.log
   ```

2. **Verify Firewall**
   - Ensure port 587 (or your SMTP port) is not blocked

3. **Namecheap Private Email Setup**
   - Make sure your domain's DNS is properly configured
   - Verify your email account is active and not suspended
   - Check if SMTP is enabled for your account
   - Try both ports: 587 (TLS) or 465 (SSL) if 587 doesn't work
   - Verify your email account is active and not suspended
   - Try both ports: 587 (TLS) or 465 (SSL) if 587 doesn't work
   - Check if your Namecheap account has SMTP restrictions

4. **Test Credentials**
   - Try connecting to SMTP manually using an email client first

5. **Email Logs**
   - All failed emails are logged in `logs/error.log`
   - Look for `email_failed` entries

---

## Security Best Practices

1. ✅ **Use .env file** - Never hardcode credentials in code
2. ✅ **Use strong passwords** - For your email account
3. ✅ **Enable TLS** - Use encryption for SMTP connections
4. ✅ **Protect .env** - Never commit to version control
5. ✅ **Monitor Logs** - Check for failed emails regularly

---

## Additional Email Functions

The system provides these functions for custom emails:

```php
// Send custom email
sendHtmlEmail(
    $to,           // Email address
    $subject,      // Email subject
    $html,         // HTML content
    $from          // From address (optional)
);

// Send welcome email to admin
sendWelcomeEmail($companyName, $companyEmail, $adminName, $adminEmail, $loginUrl);

// Send welcome email to company
sendCompanyWelcomeEmail($companyName, $companyEmail, $loginUrl);
```

---

## Next Steps

1. ✅ Configure `.env` with your SMTP settings
2. ✅ Test by registering a new company
3. ✅ Verify emails arrive in inboxes
4. ✅ Monitor `logs/error.log` for any issues

For more help, contact your email provider's support.
