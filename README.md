# Stop Overfull Bins Reporting System

A simple web application that allows users to report overfull bins by submitting a form with their email, the bin location address, a message, and up to 5 images. The system includes address autocomplete for Australian addresses and email validation to prevent misuse.

## Features

- Clean, modern, mobile-responsive design
- Email submission with image attachments
- SendGrid integration for reliable email delivery
- User's email as FROM address for better communication
- Australian address lookup with Google Places API autocomplete
- Client-side validation
- Image preview functionality
- Configurable destination email
- Email validation system to prevent form misuse
- Database storage for form submissions
- Automatic inclusion of reported address in the email message
- Automatic image processing to optimize for email attachments
- Automatic cleanup of image files after verification
- Daily cleanup of old files via cron job

## Setup Instructions

### Prerequisites

- Web server with PHP support (Apache, Nginx, etc.)
- PHP 7.2 or higher with mail functionality enabled
- MySQL database server

### Installation

1. Clone or download this repository to your web server's document root
2. Ensure the `uploads` directory has write permissions:
   ```
   chmod 755 uploads
   ```
3. Install Composer dependencies:
   ```
   composer install
   ```
   If you encounter any issues with missing autoload files, run:
   ```
   composer dump-autoload
   ```
4. Configure your destination email, SendGrid API key, and database credentials in the `.env` file:
   ```
   dest_email=your-email@example.com
   from_address=noreply@yourdomain.com
   sendgrid_api_key=YOUR_SENDGRID_API_KEY
   db_host=localhost
   db_name=stopoverfullbins
   db_user=your_db_username
   db_pass=your_db_password
   ```

   To get a SendGrid API key:
   - Sign up for a free account at [SendGrid](https://sendgrid.com/)
   - Create an API key with "Mail Send" permissions
   - Copy the API key and paste it in your `.env` file
5. Set up the database by running the setup script:
   ```
   php server/setup_db.php
   ```

### Configuration

The application uses a `.env` file to store configuration settings:

- `dest_email`: The email address where form submissions will be sent
- `from_address`: The email address used as the sender (default: noreply@stopoverfullbins.au)
- `sendgrid_api_key`: Your SendGrid API key for sending emails
- `db_host`: MySQL database host (default: localhost)
- `db_name`: MySQL database name (default: stopoverfullbins)
- `db_user`: MySQL database username
- `db_pass`: MySQL database password
- `default_message`: The default message template for the form

## Usage

1. Open the website in a web browser
2. Fill in your council registered email
3. Enter the address where the overfull bin is located (with autocomplete assistance)
4. Edit the message if needed
5. Attach up to 5 images of the overfull bin
6. Click "Submit Report"
7. Check your email for a verification message
8. Click the verification link in the email to validate your email address and send your report to the council
9. You'll receive a confirmation email once your report has been sent to the council

## Email Validation Process

The system implements a two-step verification process:

1. When a user submits the form, the data is stored in the database and a verification email is sent to the user
2. The verification email contains a preview of the submission and a unique verification link
3. When the user clicks the verification link, the system verifies the token, sends the report to the council, and sends a confirmation email to the user
4. This process ensures that only valid email addresses can submit reports to the council

## File Structure

```
stopoverfullbins/
├── css/
│   └── styles.css
├── js/
│   └── script.js
├── server/
│   ├── submit.php
│   ├── verify.php
│   ├── setup_db.php
│   ├── update_db.php
│   ├── image_utils.php
│   └── cleanup.php
├── uploads/
├── .env
├── .htaccess
├── index.html
└── README.md
```

## Development

### Client-Side

- `index.html`: Contains the form structure
- `css/styles.css`: Contains all styling for the application
- `js/script.js`: Handles client-side validation, image preview, and form submission

### Server-Side

- `server/submit.php`: Processes form submissions, handles file uploads, and stores data in the database
- `server/verify.php`: Handles email verification, sends the actual email to the council, and updates the database
- `server/setup_db.php`: Sets up the database structure
- `server/update_db.php`: Updates the database structure to add the address column if it's missing
- `server/image_utils.php`: Contains utility functions for image processing and file management
- `server/cleanup.php`: Script to remove old files from the uploads directory (run via cron job)
- `.env`: Configuration file for destination email and database credentials
- `.htaccess`: Contains security settings and PHP configurations

## Database Structure

The application uses two tables:

1. `submissions`: Stores form submissions with verification status
   - `id`: Unique identifier for the submission
   - `email`: User's email address
   - `address`: The address where the overfull bin is located
   - `message`: The message content
   - `verification_token`: Unique token for email verification
   - `verified`: Flag indicating if the email has been verified
   - `created_at`: Timestamp when the submission was created
   - `verified_at`: Timestamp when the submission was verified

2. `images`: Stores information about uploaded images
   - `id`: Unique identifier for the image
   - `submission_id`: Foreign key to the submissions table
   - `filename`: The filename on the server
   - `original_filename`: The original filename from the user
   - `file_path`: The path to the file on the server
   - `created_at`: Timestamp when the image was uploaded

## Google Places API Integration

The application uses the Google Places API for address autocomplete functionality. This helps users enter accurate Australian addresses when reporting overfull bins.

### Setting Up Google Places API

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the "Places API" for your project
4. Create an API key in the "Credentials" section
5. Replace `YOUR_API_KEY` in the `index.html` file with your actual API key:
   ```html
   <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places&callback=initAutocomplete" async defer></script>
   ```
6. (Optional but recommended) Restrict your API key to:
   - HTTP referrers (your website domain)
   - Specific APIs (Places API)

For more information, see the [Google Maps Platform documentation](https://developers.google.com/maps/documentation/javascript/get-api-key).

## Image Processing and Cleanup

The application includes automatic image processing and cleanup features to optimize email attachments and manage disk space:

### Image Processing

When images are uploaded, they are automatically processed to make them suitable for email attachments:

- Large images are resized to a maximum of 1200x1200 pixels
- Images are compressed to reduce file size
- Images are converted to JPEG format for better compatibility

This processing helps ensure that emails with attachments are delivered successfully and don't exceed size limits.

### Automatic Cleanup

The application includes two cleanup mechanisms:

1. **Post-verification cleanup**: After a submission is verified and emails are sent, the image files are automatically deleted from the server.

2. **Daily cleanup cron job**: A cleanup script removes any files older than 24 hours from the uploads directory.

### Setting Up the Cron Job

To set up the daily cleanup cron job:

1. Access your server's crontab:
   ```
   crontab -e
   ```

2. Add the following line to run the cleanup script daily at midnight:
   ```
   0 0 * * * php /path/to/stopoverfullbins/server/cleanup.php
   ```

   Replace `/path/to/stopoverfullbins` with the actual path to your installation.

3. Save and exit the editor.

You can also specify a custom age (in seconds) for file deletion:
```
0 0 * * * php /path/to/stopoverfullbins/server/cleanup.php 43200
```
This example would delete files older than 12 hours (43200 seconds).

## Troubleshooting

- If emails are not being sent, check the following:
  - Verify your SendGrid API key is correct in the `.env` file
  - Check if your SendGrid account is active and not suspended
  - Ensure your SendGrid API key has "Mail Send" permissions
  - Check PHP error logs for any SendGrid API errors
  - If SendGrid is not working, the system will fall back to PHP's mail() function, so check your server's mail configuration
- Make sure the `uploads` directory has proper write permissions
- Check PHP error logs for any issues with the form submission
- If database connection fails, verify your database credentials in the `.env` file
- Run the setup script again if you encounter database structure issues
- If you encounter a "Column not found: 1054 Unknown column 'address' in 'INSERT INTO'" error, run the database update script:
  ```
  php server/update_db.php
  ```
  This will add the address column to your existing database if it's missing.
- If you encounter a "Failed opening required '/path/to/vendor/autoload.php'" error, run:
  ```
  composer dump-autoload
  ```
  This will regenerate the autoload files without reinstalling all dependencies.
- If address autocomplete is not working, verify your Google Places API key is correct and properly configured
- If image processing fails, ensure that the GD library is installed and enabled in PHP:
  ```
  sudo apt-get install php-gd    # For Debian/Ubuntu
  sudo yum install php-gd        # For CentOS/RHEL
  ```
  Then restart your web server.
- If you're having issues with the FROM email address being rejected, this might be due to email authentication policies. The system is configured to use the user's email as the FROM address when possible, but some email providers may reject emails with unverified FROM addresses. In this case, the system will fall back to using the default FROM address.
