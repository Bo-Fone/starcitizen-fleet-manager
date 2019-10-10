variable "AWS_ACCESS_KEY" {}
variable "AWS_SECRET_KEY" {}
variable "AWS_REGION" {
  default = "eu-west-3"
}
variable "AWS_PUBLIC_KEY_PATH" {
  default = "ssh_key.pub"
}
variable "AWS_PRIVATE_KEY_PATH" {
  default = "ssh_key"
}

provider "aws" {
  access_key = var.AWS_ACCESS_KEY
  secret_key = var.AWS_SECRET_KEY
  region = var.AWS_REGION
}

resource "aws_key_pair" "aws_ssh" {
  key_name = "aws_ssh"
  public_key = file(var.AWS_PUBLIC_KEY_PATH)
}

resource "aws_instance" "example" {
  ami = "ami-0ad37dbbe571ce2a1"
  instance_type = "t2.micro"
  key_name = "aws_ssh"
  connection {
    user = "ubuntu"
    private_key = file(var.AWS_PRIVATE_KEY_PATH)
  }

  provisioner "file" {
    source = "script.sh"
    destination = "/tmp/script.sh"
  }
  provisioner "remote-exec" {
    inline = [
      "chmod +x /tmp/script.sh",
      "sudo /tmp/script.sh"
    ]
  }
}
