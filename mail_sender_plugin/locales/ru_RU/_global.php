<?php
/*
 * Global lang file
 * This file was generated automatically from messages.po
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

$trans['ru_RU'] = array(
'__meta__' => array('format_version'=>1, 'charset'=>'utf-8'),
'' => "Project-Id-Version: Mail Sender plugin v0.5\nReport-Msgid-Bugs-To: \nPOT-Creation-Date: 2012-02-24 19:29-0500\nPO-Revision-Date: 2012-02-24 19:39-0500\nLast-Translator: Alex (sam2kb)\nLanguage-Team: Russian b2evolution\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\nX-Poedit-Language: Russian\nX-Poedit-Country: RUSSIAN FEDERATION\n",
'Send emails to registered users' => 'Отправка сообщений зарегистрированным пользователям',
'Enable attachments' => 'Разрешить вложения',
'Do you want to send emails with attachments?' => 'Хотите ли вы посылать сообщения с вложениями?',
'Email charset' => 'Кодировка сообщений',
'Example: utf-8, iso-8859-1, windows-1251 etc. Note: it\'s highly recommended to use utf-8 here.' => 'Пример: utf-8, iso-8859-1, windows-1251 и др. Внимание: крайне рекоммендуется использовать кодировку utf-8.',
'Report email' => 'Email для отчетов',
'Send detailed reports to this email address. Leave empty to disable.' => 'Отправлять подробные отчеты на этот адрес. Оставьте пустым чтобы не получать отчеты.',
'Server settings' => 'Настройки сервера',
'Method to send mail' => 'Способ отправки писем',
'PHP mail (default)' => 'PHP mail (по умолчанию)',
'Sendmail path' => 'Путь к sendmail',
'Path of the sendmail program' => 'Путь к программе sendmail',
'SMTP Host' => 'SMTP хост',
'Sets the SMTP hosts<br />All hosts must be separated by a semicolon. You can also specify a different port for each host by using this format: %s. Hosts will be tried in order.' => 'Введите SMTP хост(ы)<br />Значения должны быть разделены точкой с запятой. Вы можете задать номер порта для каждого хоста индивидуально в формате: %s. Хосты будут использованы по порядку.',
'SMTP Port' => 'SMTP порт',
'Default SMTP server port' => 'Порт SMTP сервера',
'Please enter valid port number' => 'Пожалуйста, введите корректное значение порта',
'Security settings' => 'Защищенное подключение',
'None (default)' => 'нет (по умолчанию)',
'SMTP username' => 'SMTP пользователь',
'SMTP password' => 'SMTP пароль',
'Defaults' => 'Настройки показа',
'Sender\'s name' => 'Имя отправителя',
'Site Administrator' => 'Администратор сайта',
'Sender\'s email address' => 'Email адрес отправителя',
'Message subject' => 'Тема сообщения',
'Message text' => 'Текст сообщения',
'Exclude users/groups' => 'Исключить плльзователей и группы',
'Exclude groups' => 'Исключить группы',
'Don\'t send emails to selected user groups. Use "CTRL" and "SHIFT" keys to select multiple items.' => 'Исключить следующие группы позователей (сообщения не отправляются). Используйте кнопки "CTRL" и "SHIFT" для выбора нескольких вариантов.',
'Exclude users' => 'Исключить пользователей',
'Don\'t send emails to selected users. Use "CTRL" and "SHIFT" keys to select multiple items.' => 'Исключить следующих пользователей (сообщения не отправляются). Используйте кнопки "CTRL" и "SHIFT" для выбора нескольких вариантов.',
'Receive emails' => 'Получать сообщения',
'Check this if you want to receive emails from site administrator.' => 'Отметьте чтобы получать служебные сообщеня от администратора сайта.',
'Report email address is invalid!' => 'Не правильный email адрес отчета!',
'You have been unsubscribed from our mailing list!' => 'Ваша подписка была удалена!',
'The Mail Sender plugin is disabled or uninstalled' => 'Плагин Mail Sender или отключен или удален',
'Requested user not found' => 'Запрошенный пользователь не найден',
'You\'re not allowed to send emails!' => 'У вас не достаточно прав на отправку сообщений!',
'File lock removed.' => 'Блокировка снята.',
'Upload is disabled.' => 'Загрузка отключена.',
'You have no permission to add/upload files.' => 'У вас нет прав на добавление / загрузку файлов.',
"<p>It seems like another process is already sending emails. Please wait for it to complete.</p>\n<p>If you beleve this is a mistake <a %s>click here</a> to remove the lock!</p>" => "<p>Похоже, другой процесс все еще отправляет сообщения. Пожалуйста, дождитесь его окончания.</p>\n<p>Если вы считаете это сообщение ошибкой, <a %s>нажмите сюда</a> для удаления блокировки!</p>",
'File deleted.' => 'Файл удален.',
'File settings deleted.' => 'Настройки файла удалены.',
'The file exceeds the upload_max_filesize directive in php.ini.' => 'Файл превышает директиву upload_max_filesize  в php.ini.',
'The file was only partially uploaded.' => 'Файл был загружен лишь частично.',
'No file was uploaded.' => 'Файлы не были загружены.',
'Missing a temporary folder (upload_tmp_dir in php.ini).' => 'Отсутствует временная папка (upload_tmp_dir в php.ini).',
'Unknown error.' => 'Неизвестная ошибка.',
'Total attachments size should not exceed %sMb!' => 'Общий размер вложений не должен превышать %Мб!',
'The file &laquo;%s&raquo; has been successfully uploaded.' => 'Файл &laquo;%s&raquo; был успешно загружен.',
'An unknown error occurred when moving the uploaded file on the server.' => 'Неизвестная ошибка произошла при перемещении загруженного файла на сервере.',
'The file does not seem to be a valid upload! It may exceed the upload_max_filesize directive in php.ini.' => 'Файл по видимому загрузился не целиком! Возможно он превысил директиву upload_max_filesize в php.ini.',
'Select a file first.' => 'Вы не выбрали файл.',
'All done!' => 'Готово!',
'Successfull: %sFailed: %sTotal: %s' => 'Отправлено: %sНе отправлено: %sВсего: %s',
'All fields are mandatory' => 'Все поля толжны быть заполнены',
'Mail Sender' => 'Mail Sender',
'Starting email' => 'Стартовая позиция',
'Start sending from this email, skipping all previous.' => 'Начать отправку с этой позиции, пропуская предыдущие адреса.',
'Limit' => 'Количество сообщений',
'The number of emails you want to send at a time. Set 0 to send all available.' => 'Число сообщений, которое вы хотите отправить за один раз. Используйте 0 чтобы отправить все.',
'Send as text' => 'Посылать как текст',
'Send email as plain text.' => 'Посылать сообщения в виде простого текста.',
'Send as HTML' => 'Посылать как HTML',
'Send email as HTML formatted text.' => 'Посылать сообщения в виде форматированного текста (HTML).',
'From email' => 'Email отправителя',
'Sender\'s email.' => 'Email адрес отправителя.',
'From name' => 'Имя отправителя',
'Sender\'s name.' => 'Имя отправителя.',
'Subject' => 'Тема',
'Emails source' => 'Источник email адресов',
'Get emails list from selected source' => 'Взять список email адресов из внешнего источника',
'Choose a file' => 'Выберите файл',
'Attach file' => 'Прикрепить файл',
'KB' => 'Кб',
'Delete this file' => 'Удалить этот файл',
'Total size' => 'Общий размер',
'Email addresses' => 'Email адреса',
'One email address per line.' => 'Один адрес на строку (разделять переносом строки).',
'Send message !' => 'Отправить сообщение !',
'Reset' => 'Сбросить',
'The file %s cannot be read!' => 'Файл %s не может быть прочитан!',
'No emails found. Nothing to do.' => 'Адреса не найдены. Отправка отменена.',
'There are only %d emails found. Decrease the starting email number.' => 'Нашлось только %d адресов для отправки. Уменьшите номер стартового адреса.',
'OK ( %s )' => 'OK ( %s )',
'Sorry, could not send email ( %s )' => 'Извините, невозможно отправить email ( %s )',
'Mail Sender report from %s' => 'Mail Sender отчет с %s',
'Mail Sender report' => 'Mail Sender отчет',
'File not found' => 'Файл не найден',
'You must create the following directory with write permissions (777):%s' => 'Вы должны создать следующую директорию с разрешениями на запись (777):%s',

);
?>