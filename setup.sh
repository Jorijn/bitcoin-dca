#!/usr/bin/env bash

###########################################################################################
# Bitcoin DCA for Linux installation script
#
# NOTE: Make sure to verify the contents of the script
#       you downloaded matches the contents of setup.sh
#       located at https://github.com/Jorijn/bitcoin-dca/blob/master/setup.sh
#       before executing.
###########################################################################################

trap 'show_support_text' ERR
trap 'remove_temp_configuration_file' EXIT
trap 'exit' SIGINT

RED=$(tput setaf 1)
GREEN=$(tput setaf 2)
NC=$(tput sgr0)
DOCKER_IMAGE="jorijn/bitcoin-dca:latest"
APPLICATION_DIR="${HOME}/.bitcoin-dca"
CONFIGURATION_LOCATION="${APPLICATION_DIR}/configuration.conf"
DATA_LOCATION="${APPLICATION_DIR}/data"
FLAG_PERSISTENT_STORAGE=0
CONFIGURATION_FILE=$(mktemp)

function show_support_text() {
  echo
  echo "${RED}-------------------------------------------------------------------------------------${NC}"
  echo "${RED}[!]${NC} It looks like something went wrong. If you are unsure what happened, feel free to"
  echo "${RED}[!]${NC} reach out to me by email at jorijn@jorijn.com or visit the Telegram support chat"
  echo "${RED}[!]${NC} at https://t.me/bitcoindca"

  remove_temp_configuration_file

  exit 1
}

command_exists() {
  command -v "$@" >/dev/null 2>&1
}

check_if_running_under_root() {
  if [[ $EUID -eq 0 ]]; then
    echo
    echo "Please do not run this script as root. ☹️"

    show_support_text
  fi

  return 0
}

function exit() {
  remove_temp_configuration_file

  exit
}

check_docker_runnable_under_own_user() {
  if docker version &>/dev/null; then
    return 0
  fi

  your_user=your-user
  [ "$user" != 'root' ] && your_user="$user"
  # intentionally mixed spaces and tabs here -- tabs are stripped by "<<-EOF", spaces are kept in the output
  echo
  echo "${GREEN}==================================================================================================${NC}"
  echo "${GREEN}⚠️  It looks like we need a manual step before going further.${NC}"
  echo "${GREEN}==================================================================================================${NC}"
  echo "For scheduling Docker tasks under your own user, you should add your own user to the Docker group:"
  echo
  echo "$ sudo usermod -aG docker $your_user"
  echo
  echo "Remember that you will have to log out and back in for this to take effect!"
  echo "Re-run the setup script after your done."
  echo
  echo "WARNING: Adding a user to the \"docker\" group will grant the ability to run"
  echo "         containers which can be used to obtain root privileges on the"
  echo "         docker host."
  echo "         Refer to https://docs.docker.com/engine/security/security/#docker-daemon-attack-surface"
  echo "         for more information."

  show_support_text
}

function check_if_docker_is_installed() {
  if command_exists docker; then
    # shellcheck disable=SC2230
    export DOCKER=$(which docker)
    return 0
  fi

  echo "To run Bitcoin DCA you need Docker and it looks like Docker is not installed on this machine."
  echo
  echo "To install Docker, run the following commands:"
  echo
  echo "$ curl -fsSL https://get.docker.com -o get-docker.sh"
  echo "$ sh get-docker.sh"

  show_support_text
}

function pull_image_from_registry() {
  echo
  #  docker pull $DOCKER_IMAGE
  echo
}

function check_application_directory() {
  if [ ! -e "$APPLICATION_DIR" ]; then
    return 0
  fi

  echo
  echo "🤔 It looks like the application directory ${GREEN}${APPLICATION_DIR}${NC} already exists.."
  echo "If you want to setup a new installation please remove this directory before going any further."

  show_support_text
}

function verify() {
  while true; do
    read -rp "❓ $1 [yes/no]: " yn

    case $yn in
    [Yy]*)
      return 0
      break
      ;;
    [Nn]*)
      return 1
      break
      ;;
    *) echo "Please answer yes or no." ;;
    esac
  done
}

function ask() {
  while true; do
    read -rp "❓ $1: " answer

    if [ -n "$answer" ]; then
      echo "$answer"
      break
    fi

    if [ -n "$2" ]; then
      echo "$2"
      break
    fi
  done
}

function configuration_pick_an_exchange() {
  echo
  echo "💵 First, we need to pick on which Exchange the Bitcoin DCA tool will run."

  PS3='Please enter your choice: '
  options=("BL3P" "Bitvavo" "I changed my mind, quit")
  select opt in "${options[@]}"; do
    case $opt in
    "BL3P")
      configure_exchange_bl3p
      return
      ;;
    "Bitvavo")
      configure_exchange_bitvavo
      return
      ;;
    "I changed my mind, quit")
      exit 0
      ;;
    *) echo "☠️  Invalid option: $REPLY" ;;
    esac
  done
}

function configuration_pick_an_withdraw_method() {
  echo "Eventually when your Dollar Cost Averaging you will want to withdraw your purchased satoshis to"
  echo "a hardware wallet. Here, you may choose between either a static wallet address or derive a new address"
  echo "every time a withdrawal is made."
  echo
  echo "Wallet address:  If your unsure about what Xpub means, pick this option. Your hardware wallet should"
  echo "                 give you an address for receiving funds into. This tool will instruct the exchange to"
  echo "                 send your satoshis to this address every time a withdrawal is made."
  echo
  echo "xPub derivation: Although a bit more complex to set up, this is the recommended way to go. Your HD wallet"
  echo "                 contains many key pairs and by configuring Bitcoin DCA to use your xPub it will instruct"
  echo "                 the exchange to use a new fresh address every time a withdrawal is made. This improves"
  echo "                 your privacy as the blockchain is a public ledger and everyone could know how much you"
  echo "                 have saved eventually."
  echo

  PS3='Please enter your choice: '
  options=("Wallet address" "xPub derivation" "I changed my mind, quit")
  select opt in "${options[@]}"; do
    case $opt in
    "Wallet address")
      configure_simple_wallet_address
      return 0
      ;;
    "xPub derivation")
      configure_xpub_wallet_address
      return 0
      ;;
    "I changed my mind, quit")
      exit 0
      ;;
    *) echo "☠️  Invalid option: $REPLY" ;;
    esac
  done
}

function configure_simple_wallet_address() {
  local wallet_address

  echo
  wallet_address=$(ask "What is your Bitcoin wallet address")
  echo "WITHDRAW_ADDRESS=$wallet_address" >>"$CONFIGURATION_FILE"
}

function configure_xpub_wallet_address() {
  local xpub_address

  export FLAG_PERSISTENT_STORAGE=1

  echo
  echo "💡 Need some help figuring out where your xPub is located? See this blog post:"
  echo "${GREEN}https://blog.blockonomics.co/how-to-find-your-xpub-key-with-these-8-popular-bitcoin-wallets-ce8ea665ffdc${NC}"
  echo

  xpub_address=$(ask "What is your Bitcoin xPub")
  echo "WITHDRAW_XPUB=$xpub_address" >>"$CONFIGURATION_FILE"

  run_tool_with_test_configuration verify-xpub
  if test $? -ne 0; then
    echo
    echo "🚫 It could be that there is a problem with the derivation mechanism or that you didn't enter the xPub correctly."
    echo "   Please try again or reach out to me for support."
    show_support_text
    exit 1
  fi

  verify "Does the list displayed here match the one in your wallet client"
  if test $? -ne 0; then
    echo
    echo "🚫 It could be that there is a problem with the derivation mechanism or that you didn't enter the xPub correctly."
    echo "   Please try again or reach out to me for support."
    show_support_text
    exit 1
  fi
}

function start_configuration_wizard() {
  configuration_pick_an_exchange

  echo
  echo "✅ OK! Will try and get your balance to see if we're connected."
  echo

  run_tool_with_test_configuration balance
  if test $? -ne 0; then
    echo "😧 Something happened, are your API credentials correctly entered? Please verify them and restart the setup wizard."
    show_support_text
    exit 1
  fi

  echo "✅ OK! Balance check is looking good."
  echo

  configuration_pick_an_withdraw_method
  echo
}

function remove_temp_configuration_file() {
  if [ ! -e "$CONFIGURATION_FILE" ]; then
    return 0
  fi

  rm "$CONFIGURATION_FILE"
}

function configure_exchange_bl3p() {
  local api_key
  local api_secret

  echo
  echo "EXCHANGE=bl3p" >>"$CONFIGURATION_FILE"

  echo "💵 Great choice! Let's configure the API connection."
  echo

  api_key=$(ask "What is your BL3P API Identifier Key")
  echo "BL3P_PUBLIC_KEY=$api_key" >>"$CONFIGURATION_FILE"

  api_secret=$(ask "What is your BL3P API Private Key")
  echo "BL3P_PRIVATE_KEY=$api_secret" >>"$CONFIGURATION_FILE"
}

function run_tool_with_test_configuration() {
  $DOCKER run --rm -it --env-file "$CONFIGURATION_FILE" $DOCKER_IMAGE "$@"
}

function configure_exchange_bitvavo() {
  local api_key
  local api_secret

  echo
  echo "EXCHANGE=bitvavo" >>"$CONFIGURATION_FILE"

  echo "💵 Great choice! Let's configure the API connection."
  echo

  api_key=$(ask "What is your Bitvavo API Key")
  echo "BITVAVO_API_KEY=$api_key" >>"$CONFIGURATION_FILE"

  api_secret=$(ask "What is your Bitvavo API Secret")
  echo "BITVAVO_API_SECRET=$api_secret" >>"$CONFIGURATION_FILE"
}

function install_tool() {
  mkdir -p "$APPLICATION_DIR"

  if [ "$FLAG_PERSISTENT_STORAGE" -eq 1 ]; then
    mkdir -p "$DATA_LOCATION"
  fi

  cat "$CONFIGURATION_FILE" >"$CONFIGURATION_LOCATION"

  persistent_storage_argument=""
  if [ "$FLAG_PERSISTENT_STORAGE" -eq 1 ]; then
    persistent_storage_argument=" -v $DATA_LOCATION:/var/storage"
  fi

  export TOOL_COMMAND_INTERACTIVE="${DOCKER} run --rm -it${persistent_storage_argument} --env-file ${CONFIGURATION_LOCATION} ${DOCKER_IMAGE}"
  export TOOL_COMMAND="${DOCKER} run --rm${persistent_storage_argument} --env-file ${CONFIGURATION_LOCATION} ${DOCKER_IMAGE}"
}

do_install() {
  echo "🤖 Checking if we are not running under root.."
  check_if_running_under_root

  echo "🤖 Checking if Docker is installed.."
  check_if_docker_is_installed

  echo "🤖 Checking if your local user can talk to the Docker daemon.."
  check_docker_runnable_under_own_user

  echo "🤖 Pulling the Bitcoin DCA tool from the registry.."
  pull_image_from_registry

  echo "🤖 Setting up the application directory.."
  check_application_directory

  echo "🤖 Starting the configuration wizard.."
  start_configuration_wizard

  echo "🤖 Installing the configuration"
  install_tool

  clear
  echo "🎉 Bitcoin DCA was successfully configured and installed to ${GREEN}${APPLICATION_DIR}${NC}."
  echo
  echo "You can now follow the rest of the steps from the Getting Started guide to test buying, withdrawing and"
  echo "setting up recurring purchases."
  echo
  echo "${GREEN}https://bitcoin-dca.readthedocs.io/en/latest/getting-started.html${NC}"
  echo
  echo "These are your personalized commands:"
  echo
  echo "  ${GREEN}Running the tool from your terminal:${NC}"
  echo "  $ ${TOOL_COMMAND_INTERACTIVE}"
  echo
  echo "  ${GREEN}Running the tool on a schedule from a cronjob:${NC}"
  echo "  $ ${TOOL_COMMAND}"
  echo
  echo "Do you have any questions? Reach out to me by email using ${GREEN}jorijn@jorijn.com${NC} or visit the"
  echo "Telegram support chat at ${GREEN}https://t.me/bitcoindca${NC}"
  echo
  echo "Happy DCA-ing! 👋"
}

echo "${GREEN}====================================================================================${NC}"
echo "${GREEN}💰 Installation script for Bitcoin DCA — https://github.com/Jorijn/bitcoin-dca"
echo "${GREEN}====================================================================================${NC}"

# wrapped up in a function so that we have some protection against only getting
# half the file during "curl | sh"
do_install
