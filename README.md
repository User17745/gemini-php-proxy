# PHP Gemini Proxy

This project is a PHP-based proxy for interacting with the Gemini API.

## Requirements

- PHP 7.4 or higher
- Composer

## Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   ```

2. Navigate to the project directory:
   ```bash
   cd PHP-Gemini-Proxy
   ```

3. Install dependencies using Composer:
   ```bash
   composer install
   ```

4. Create a `.env` file in the project root and add your Gemini API key:
   ```env
   GEMINI_API_KEY=your_api_key_here
   ```

## Usage

1. Start a local PHP server:
   ```bash
   php -S localhost:8000
   ```

2. Send POST requests to the endpoint:
   ```bash
   curl -X POST -H "Content-Type: application/json" -d '{"prompt": "Your prompt here"}' http://localhost:8000/gemini_proxy.php
   ```

## Logging

Requests and responses are logged in `gemini_log.csv` for debugging purposes.

## License

This project is licensed under the MIT License.
