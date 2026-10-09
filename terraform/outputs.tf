output "site_url" {
  value = "https://${azurerm_public_ip.pip.fqdn}"
}

output "public_ip" {
  value = azurerm_public_ip.pip.ip_address
}

output "ssh_command" {
  value = "ssh ${var.admin_username}@${azurerm_public_ip.pip.fqdn}"
}
