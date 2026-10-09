variable "subscription_id" {
  type        = string
  description = "Azure subscription ID (az account show --query id -o tsv)"
}

variable "location" {
  type        = string
  default     = "southeastasia"
  description = "Azure region. Azure for Students may only allow some regions; if creation fails, try eastasia, japaneast, or another allowed region."
}

variable "prefix" {
  type    = string
  default = "stockspot"
}

variable "dns_label" {
  type        = string
  description = "Globally unique label; gives you <label>.<region>.cloudapp.azure.com (lowercase letters, numbers, dashes)"
}

variable "vm_size" {
  type        = string
  default     = "Standard_B1s"
  description = "Small burstable VM. Check availability with: az vm list-skus -l <region> --size Standard_B1 -o table"
}

variable "admin_username" {
  type    = string
  default = "azureuser"
}

variable "ssh_public_key" {
  type        = string
  description = "Contents of your ~/.ssh/id_ed25519.pub"
}

variable "ssh_allowed_cidr" {
  type        = string
  description = "Who may SSH in. Use your public IP as a /32 (e.g. 203.0.113.7/32). Use * only if GitHub Actions must deploy."
}

variable "repo_url" {
  type        = string
  description = "HTTPS URL of your public GitHub repo containing this project"
}

variable "enable_auto_shutdown" {
  type        = bool
  default     = false
  description = "Shut the VM down every night to save student credits"
}
