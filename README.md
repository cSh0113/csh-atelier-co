# 🌿 CSH Atelier Co.

## Circular Fashion Marketplace Reduce. Reuse. Recycle. ReWear.

```
   ╔════════════════════════════════════════════════════════╗
   ║                                                        ║
   ║         CSH ATELIER CO. CIRCULAR FASHION              ║
   ║                                                        ║
   ║   Buy • Sell • Donate • Rewear Preloved Clothing      ║
   ║                                                        ║
   ╚════════════════════════════════════════════════════════╝
```

---

##  What is CSH Atelier Co.?

**CSH Atelier Co.** is a sustainable e-commerce platform built for the conscious consumer and the planet. We operate as a hybrid marketplace where members buy and sell preloved clothing (C2C), discover brand pieces with social messages (B2C), and donate old garments for store credits (C2B).

Every transaction on our platform supports environmental awareness and social causes.

---

##  Features

###  Community Marketplace (C2C)
- Members list and sell their preloved clothing
- Direct peer-to-peer transactions with store credits
- Seller ratings and reviews
- Secure messaging between buyers and sellers

###  Brand Collection (B2C)
- Curated pieces made from recycled and sustainable materials
- Each item carries a social message (GBV awareness, mental health, environmental initiatives)
- Transparently sourced and remade from donations
- Featured collections highlight our causes

###  Donation Program (C2B)
- Donate old clothing to CSH
- Earn store credits for every donation
- Items are remade into brand pieces or responsibly recycled
- Close the loop on fashion waste

###  Smart Payments
- Paystack card payments (Visa, Mastercard)
- Store credit system (earn by selling or donating, spend on any purchase)
- 1 credit = R0.10 (10:1 rate)
- Transparent pricing with no hidden fees

###  Shipping & Logistics
- Flat-rate delivery (R150 across South Africa)
- Free collection available for member listings
- Multiple courier options (The Courier Guy, PostNet, Aramex, etc.)
- Order tracking and notifications

###  Admin Dashboard
- Moderation tools for listings and donations
- Real-time order management
- Seller payout tracking
- Detailed analytics and cash flow reports
- Member reporting and dispute resolution

---

##  Tech Stack

| Component | Technology |
|-----------|------------|
| **Frontend** | HTML5, CSS3, Vanilla JavaScript |
| **Backend** | PHP 8.1 (no framework) |
| **Database** | MySQL 8 (InnoDB) |
| **Payments** | Paystack API |
| **Authentication** | Email/Password + Google OAuth |
| **Hosting** | InfinityFree (PHP + MySQL) |
| **Design** | Morphism (Neumorphism/Glassmorphism) |
| **Code Style** | Custom CSS properties, prepared statements, CSRF protection |

### Why no framework?
This site was built for the **Eduvos ITECA3-12 module**, which requires vanilla HTML, CSS, and PHP. No CMS tools, no frameworks—just clean, intentional code.

---

##  Live Site

Visit **[CSH Atelier Co.](https://cshatelier.ct.ws or https://cshatelierco.ct.ws or https://rewear.ct.ws)** to start buying, selling, and donating.

**Admin Access:** https://cshatelier.ct.ws/admin/

---

##  Built By

### Developed by CSH 
**CSH Innovations Co.**

**CSH Innovations Co.** is a South African multi-sector company built on the **F.A.T.E.** framework:

- **Fashion** CSH Atelier Co. (this platform)
- **Art** Creative collaborations and digital experiences
- **Tech** Custom software and digital solutions
- **Entertainment** Content, media, and interactive experiences

CSH Innovations operates as a transparent, mission-driven venture building digital products that align with social and environmental values.

---

##  Founder & Developer

**Sibusiso Hector Chauke** Founder & Lead Developer

- Location: Gauteng, South Africa
- Education: Eduvos (Computer Science student)
- Focus: Full-stack e-commerce, sustainable business models, community-driven platforms

---

##  Key Metrics

- **15 database tables** with relational integrity
- **9 admin modules** for complete platform control
- **3-tier marketplace** (C2C, B2C, C2B)
- **2 authentication methods** (email + Google OAuth)
- **1 payment provider** (Paystack, PCI-compliant)
- **100% custom code** — no templates, no shortcuts

---

##  Quick Start (For Developers)

### Prerequisites
- PHP 8.1+
- MySQL 8
- Git

### Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/csho113/csh-atelier-co.git
   cd csh-atelier-co
   ```

2. **Configure the database**
   ```bash
   cp config/config.example.php config/config.php
   # Edit config/config.php with your database credentials
   ```

3. **Import the database schema**
   ```bash
   mysql -u your_user -p your_database < database/cshatelier.sql
   ```

4. **Upload to your server**
   - Use SFTP or your hosting provider's file manager
   - Upload all files to your public_html folder

5. **Set permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 temp/
   chmod 755 logs/
   ```

6. **Visit your site**
   - Frontend: `https://yourdomain.com`
   - Admin: `https://yourdomain.com/admin/` (sign up, then promote to admin in database)

---

##  Project Structure

```
csh-atelier-co/
├── admin/                    # Admin dashboard pages
│   ├── index.php            # Dashboard overview
│   ├── products.php         # Brand product management
│   ├── listings.php         # Member listing moderation
│   ├── donations.php        # Donation approvals
│   ├── orders.php           # Order history and payouts
│   ├── shipping.php         # Fulfillment and tracking
│   ├── users.php            # Member account management
│   ├── credits.php          # Store credit ledger
│   ├── cashflow.php         # Financial reports
│   └── reports.php          # Member reports and blocks
├── api/                      # API endpoints (optional)
├── config/                   # Configuration files
│   ├── config.php           # Database and app config
│   └── config.example.php   # Template (safe to commit)
├── database/                 # Database schema and migrations
│   ├── cshatelier.sql       # Main schema
│   └── upgrade-*.sql        # Version upgrades
├── includes/                 # Shared functions and templates
│   ├── functions.php        # Core business logic
│   ├── header.php           # Navigation and layout
│   └── footer.php           # Footer
├── uploads/                  # User-uploaded images (not committed)
├── logs/                     # Error and audit logs (not committed)
├── index.php                # Homepage
├── checkout.php             # Payment checkout flow
├── about.php                # About page
├── how-it-works.php         # Platform explanation
└── README.md                # This file
```

---

##  Security

- **Prepared statements** prevent SQL injection
- **CSRF tokens** protect form submissions
- **Input validation** on all user data
- **Output escaping** with custom `e()` function
- **Password hashing** with bcrypt
- **Session management** with secure cookies
- **No sensitive data in version control** (config.php excluded)

---

##  Code Guidelines

- No framework or CMS — vanilla PHP, HTML, CSS, JavaScript
- Short, intentional code comments in plain language
- CSS custom properties for consistent theming
- Morphism design patterns (neumorphism + glassmorphism)
- Prepared statements for all database queries
- No external dependencies (except Paystack and Google Auth)

---

##  Deployment

### From GitHub to Live Server

**Option A: Manual Upload**
1. Download latest release as ZIP from GitHub
2. Extract on your computer
3. Use SFTP to upload changed files to your server

**Option B: Git Pull (if SSH available)**
1. SSH into your server
2. Navigate to your web folder
3. Run: `git pull origin main`

See **GitHub-Setup-Guide.txt** for detailed deployment instructions.

---

##  Support & Contact

- **Website:** [CSH Innovations Co.](https://cshinnovations.com)
- **Email:** admin@cshinnovations.com
- **Location:** Johannesburg, Gauteng, South Africa

---

##  License

This project is proprietary to **CSH Innovations Co.** and is not open for distribution or modification without explicit written permission.

---

##  Acknowledgments

Built with intention for:
- Our community of sustainable fashion advocates
- Every member who buys, sells, and donates with us
- The planet, and everyone fighting for a circular economy
- The Eduvos community and instructors

---

##  Join the Movement

Help us build a more sustainable fashion future. Visit [CSH Atelier Co.](https://cshatelier.ct.ws) today.

**Reduce. Reuse. Recycle. ReWear.**

---

<div align="center">

###  Developed with intention by **CSH Innovations Co.** 

_Building digital products for social and environmental impact_

![CSH Innovations](https://cshinnovations.com/logo.svg)

**[Visit CSH Innovations Co.](https://cshinnovations.com)** | **[Shop CSH Atelier](https://cshatelier.ct.ws)** | **[GitHub](https://github.com/cSh0113)**

</div>

---

*Last updated: September 2026*
