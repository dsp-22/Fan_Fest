</div> <style>
        .site-footer {
            background-color: #000;
            color: #fff;
            /* FIXED SPACING: 100px top padding provides premium breathing room */
            padding: 100px 30px 60px 30px;
            margin-top: 0;
            font-family: 'Inter', sans-serif;
            /* Ties directly into your Theme Engine */
            border-top: 4px solid <?php echo $accent ?? '#990000'; ?>;
        }

        .footer-content {
            max-width: 1050px;
            margin: 0 auto;
        }

        .footer-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 50px;
            margin-bottom: 50px;
        }

        /* Large, clean header matching your Deloitte reference */
        .footer-brand h2 {
            font-size: 3.2em;
            font-weight: 200;
            margin: 0 0 15px 0;
            letter-spacing: -1.5px;
        }

        .footer-brand p {
            color: #888;
            font-size: 0.95em;
            max-width: 380px;
            line-height: 1.6;
        }

        .footer-links {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 60px;
        }

        .footer-col h4 {
            text-transform: uppercase;
            font-size: 0.75em;
            letter-spacing: 2px;
            /* Matches the page accent color */
            color: <?php echo $accent ?? '#990000'; ?>;
            margin-bottom: 25px;
            font-weight: 800;
        }

        .footer-col ul { list-style: none; padding: 0; margin: 0; }
        .footer-col ul li { margin-bottom: 12px; }
        
        .footer-col ul li a { 
            color: #bbb; 
            text-decoration: none; 
            font-size: 0.95em; 
            transition: color 0.2s ease; 
        }

        .footer-col ul li a:hover { 
            color: #fff; 
            text-decoration: underline; 
        }

        .btn-faq {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 28px;
            border: 1px solid #444;
            color: #fff;
            text-decoration: none;
            font-size: 0.85em;
            font-weight: 700;
            border-radius: 4px;
            transition: 0.3s;
        }

        .btn-faq:hover {
            background: #fff;
            color: #000;
            border-color: #fff;
        }

        .footer-bottom {
            border-top: 1px solid #222;
            padding-top: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85em;
            color: #555;
        }

        .school-tag {
            font-weight: 600;
            color: #666;
        }
    </style>

    <footer class="site-footer">
        <div class="footer-content">
            <div class="footer-top">
                <div class="footer-brand">
                    <h2>Let's connect</h2>
                    <p>Enhancing the game day experience through real-time social connection and venue-based features.</p>
                    <a href="faq.php" class="btn-faq">View FAQ & Help</a>
                </div>

                <div class="footer-links">
                    <div class="footer-col">
                        <h4>Platform</h4>
                        <ul>
                            <li><a href="team.php">About the Project</a></li>
                            <li><a href="team.php">Meet Team 06</a></li>
                            <li><a href="lightshow.php">Light Show</a></li>
                        </ul>
                    </div>
                    <div class="footer-col">
                        <h4>Fan Hub</h4>
                        <ul>
                            <li><a href="friends.php">Find Friends</a></li>
                            <li><a href="profile.php">My Fan Profile</a></li>
                            <li><a href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <div>
                    &copy; <?php echo date("Y"); ?> FanFest | Team 06 Capstone Project
                </div>
                <div class="school-tag">
                    Luddy School of Informatics, Computing, and Engineering
                </div>
            </div>
        </div>
    </footer>
</body>
</html>