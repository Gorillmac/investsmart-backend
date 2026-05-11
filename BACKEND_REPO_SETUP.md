# Backend Repository Setup

  
**Project:** InvestSmart Backend

Use the following commands in the VS Code terminal to create and push the backend repository.

## 1. Open the backend project folder

```powershell
cd C:\laragon\www\investsmart
```

## 2. Initialize Git

```powershell
git init
git branch -M main
```

## 3. Configure your Git identity if needed

```powershell
git config --global user.name "Your Full Name"
git config --global user.email "your-email@example.com"
```

## 4. Create a `.gitignore` file

```powershell
@"
.env
/vendor/
/node_modules/
*.log
.DS_Store
Thumbs.db
"@ | Set-Content .gitignore
```

## 5. Add all backend files

```powershell
git add .
```

## 6. Create the first commit

```powershell
git commit -m "Initial backend commit"
```

## 7. Connect the remote GitHub repository

Replace `YOUR_USERNAME` with your real GitHub username.

```powershell
git remote add origin https://github.com/YOUR_USERNAME/investsmart-backend.git
```

## 8. Push the backend code

```powershell
git push -u origin main
```

## 9. If the repository already exists and you are updating it later

```powershell
git add .
git commit -m "Update backend code"
git push origin main
```

## 10. Recommended backend files to include

Your backend repository should contain at least:

- `backend/`
- `database/`
- `docs/`
- `README.md`
- `.gitignore`

## 11. Important note

Before pushing, make sure your backend folder contains the latest:

- PHP API files
- database schema
- audit trail logic
- ERD files in the `docs` folder
