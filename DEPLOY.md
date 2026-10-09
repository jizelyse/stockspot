# Deploying StockSpot to Azure (Azure for Students, no credit card)

Result: the app running at `https://<your-label>.<region>.cloudapp.azure.com` on a small Ubuntu VM with
Docker, free HTTPS (Caddy / Let's Encrypt), nightly database backups, and everything created by Terraform so you can
destroy and rebuild it any time.

## 0. What you need on your computer
- [Git](https://git-scm.com), [Azure CLI](https://learn.microsoft.com/cli/azure/install-azure-cli), [Terraform](https://developer.hashicorp.com/terraform/install)
- A GitHub account (free)
- An SSH key: `ssh-keygen -t ed25519` (press Enter for defaults). Your public key is `~/.ssh/id_ed25519.pub`

## 1. Put the project on GitHub
Create a new **public** repository (e.g. `stockspot`), then in the project folder:
```bash
git init && git add . && git commit -m "StockSpot"
git branch -M main
git remote add origin https://github.com/<you>/stockspot.git
git push -u origin main
```
The VM will clone this repo. (`.env`, Terraform state and `terraform.tfvars` are git-ignored on purpose.)
GitHub Actions will also start running the tests (Actions tab).

## 2. Log in to Azure
```bash
az login                              # sign in with your Mapúa-linked account
az account show --query id -o tsv     # copy this subscription id
```

## 3. Configure Terraform
```bash
cd terraform
cp terraform.tfvars.example terraform.tfvars
```
Edit `terraform.tfvars`: subscription id, a unique `dns_label`, your SSH public key, your public IP as `x.x.x.x/32`
(search "what is my ip"), and your GitHub repo URL. Keep `location = "southeastasia"` (Singapore, closest to the
Philippines) unless Azure rejects it. Student subscriptions can restrict regions; if so try `eastasia` or another allowed one.

## 4. Create everything
```bash
terraform init
terraform plan        # read what will be created
terraform apply       # type yes
```
Terraform prints `site_url` and `ssh_command`. Wait **5 to 10 minutes**: the VM installs Docker, builds the app and
starts the database, then Caddy requests the HTTPS certificate. Open `site_url`.

## 5. First things to do after it is live
1. Log in (`owner / Owner@123`) and **change both demo passwords** immediately (Users page).
2. In the Azure portal, open **Cost Management > Budgets** and add a budget alert so you can watch your credit.
3. Run the tests against the live site if you want proof (they need DB access, so they are for local/CI; the screenshot
   script works anywhere):
   `python3 tests/capture_screenshots.py https://<your-site> owner '<password>' docs/screenshots`

## Day-to-day
| Task | Command |
|---|---|
| SSH in | `ssh azureuser@<fqdn>` |
| Update the app | on the VM: `cd stockspot && git pull && docker compose -f docker-compose.prod.yml up -d --build` |
| See logs | `docker compose -f docker-compose.prod.yml logs -f web` |
| Manual backup | `./docker/backup.sh` (nightly one runs at 02:00; keeps 7 days in `backups/`) |
| Restore a backup | `gunzip -c backups/<file>.sql.gz \| docker compose -f docker-compose.prod.yml exec -T db mariadb -uroot -p<ROOT_PASS> stockspot` |
| Pause to save credit | Azure portal: **Stop (deallocate)** the VM. Or set `enable_auto_shutdown = true` |
| Delete everything | `terraform destroy` |

## Troubleshooting
- **`terraform apply` says the VM size or region is unavailable / not allowed:** run
  `az vm list-skus -l southeastasia --size Standard_B -o table` and pick an available small size (set `vm_size`), or change `location`.
- **Site shows a certificate error for the first minutes:** Caddy is still getting the certificate. Check ports 80 and 443 are
  allowed (they are in the NSG) and that `SITE_ADDRESS` in `~/stockspot/.env` equals your FQDN.
- **Nothing is running:** SSH in and check `sudo cloud-init status --long` and `sudo tail -100 /var/log/cloud-init-output.log`.
- **Out of memory:** the VM has 1 GB RAM; a 2 GB swap file is created automatically and MariaDB is tuned (`docker/mariadb.cnf`).
- **Cannot SSH:** your home IP changed. Update `ssh_allowed_cidr` and run `terraform apply`.

## Optional: deploy from GitHub Actions
`.github/workflows/deploy.yml` can redeploy over SSH. It needs GitHub secrets `VM_HOST`, `VM_USER`, `VM_SSH_KEY`, and the
VM's firewall must allow GitHub's runners (`ssh_allowed_cidr = "*"`; login is key-only and fail2ban is installed).
If you prefer to keep SSH locked to your own IP, deploy by hand with the update command above.

## Ideas to extend it for your portfolio
Back up to Azure Blob Storage with a managed identity, add monitoring (Azure Monitor / uptime alert), put Terraform state
in a remote backend, add a staging environment, or move the container to Azure Container Apps.
