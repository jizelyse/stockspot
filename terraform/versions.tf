terraform {
  required_version = ">= 1.5"
  required_providers {
    azurerm = { source = "hashicorp/azurerm", version = "~> 4.0" }
  }
}
provider "azurerm" {
  features {}
  # Set your subscription id via TF_VAR / `az login` + `az account show --query id -o tsv`
  subscription_id = var.subscription_id
}
