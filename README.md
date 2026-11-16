# Student Resource Hub

Students register, upload PDF notes, search the library and download approved resources.

Stack: HTML, CSS, JavaScript, PHP, MySQL.

## Run locally

    brew services start mysql
    mysql -u root < database/student_hub.sql
    php -d upload_max_filesize=10M -d post_max_size=12M -S localhost:8000

Open http://localhost:8000

## Make an admin

Register first, then:

    mysql -u root student_hub -e "UPDATE users SET role='admin' WHERE email='YOUR_EMAIL';"
