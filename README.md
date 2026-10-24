<div>
  <h1>AnkerGames - Game Library Management System</h1>
  <p>
    A web-based dashboard application built with PHP and MySQL to manage a game store/library. It features a modern UI, comprehensive CRUD operations, and interactive data analytics to track user playtime and game trends.
  </p>
  
  <hr><br>

  <h2>Features</h2>
  <ul>
    <li><strong>Interactive Dashboard:</strong> Visualizes playtime analytics using <strong>Chart.js</strong> (Stacked bar charts for 12-month trends, and mini sparkline charts for 6-month playtime).</li>
    <li><strong>Game Management:</strong> Add, edit, and delete games with cover art integration (Steam CDN), pricing, and storage requirements.</li>
    <li><strong>Publisher Management:</strong> Keep track of game publishers and their contact information.</li>
    <li><strong>User Management:</strong> Manage user accounts, roles (Admin/User), and wallet balances. Includes a guard against self-deletion.</li>
    <li><strong>Secure Authentication:</strong> User registration and login system using PHP <code>password_hash()</code> with bcrypt encryption.</li>
    <li><strong>Activity Logging:</strong> Tracks actions (create, update, delete) silently in the background for auditing.</li>
    <li><strong>Responsive UI:</strong> Styled with Bootstrap 5 and custom CSS for a sleek, Steam-inspired dark-sidebar design.</li>
  </ul>

  <br><hr><br>

  <h2>Tech Stack</h2>
  <ul>
    <li><strong>Backend:</strong> PHP 8.x (using PDO for secure database interactions)</li>
    <li><strong>Database:</strong> MySQL / MariaDB</li>
    <li><strong>Frontend:</strong> HTML5, CSS3, Bootstrap 5, Bootstrap Icons</li>
    <li><strong>Data Visualization:</strong> Chart.js</li>
    <li><strong>Typography:</strong> Google Fonts (Inter)</li>
  </ul>

  <br><hr><br>

  <h2>How to Run This Project Locally</h2>
  
  <h3>Prerequisites</h3>
  <ul>
    <li><a href="https://www.apachefriends.org/index.html">XAMPP</a> (or WAMP/MAMP) installed.</li>
  </ul>

  <h3>Setup the Database</h3>
  <ol>
    <li>Open your browser and go to <a href="http://localhost/phpmyadmin">http://localhost/phpmyadmin</a>.</li>
    <li>Click on the <strong>SQL</strong> tab at the top.</li>
    <li>Copy the entire contents of the <code>sql data.txt</code> (or the provided SQL script in this repo) and paste it into the SQL box.</li>
    <li>Click <strong>Go</strong>. This will automatically create the <code>steamdb</code> database, tables, and populate them with sample analytics data.</li>
  </ol>

  <h3>Access the Application</h3>
  <p>Open your browser and navigate to:<br>
  👉 <strong><code>http://localhost/gamelibrary/login.php</code></strong></p>

  <br><hr><br>

  <h2>🔐 Default Login Credentials</h2>
  <p>Use the following sample account to log in and explore the dashboard:</p>
  <ul>
    <li><strong>Username:</strong> <code>hau</code></li>
    <li><strong>Password:</strong> <code>hau123456789</code></li>
  </ul>
  <p><em>(You can also register a new account by clicking "Create one" on the login page).</em></p>

  <br><hr><br>

  <h2>📁 File Structure</h2>
  <ul>
    <li><code>config.php</code> - Database connection setup using PDO.</li>
    <li><code>index.php</code> - The main routing file. Handles all CRUD operations, page rendering, and dashboard analytics.</li>
    <li><code>login.php</code> / <code>register.php</code> / <code>logout.php</code> - Authentication handling.</li>
    <li><code>sql data.txt</code> - The complete database schema and sample data.</li>
  </ul>

  <br><hr><br>
</div>
