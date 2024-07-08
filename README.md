# GitLab integration into Nextcloud

🦊 Put a fox in your engine!

This app adds a dashboard item to see your most important GitLab notifications and a unified search provider for repositories, issues and merge requests.

## 🔧 Configuration

### User settings

The account configuration happens in the "Connected accounts" user settings section. It requires to create a personal access token in your GitLab settings.

A link to the "Connected accounts" user settings section will be displayed in the widget for users who didn't configure a GitLab account.

### Admin settings

There also is a "Connected accounts" **admin** settings section if you want to allow your Nextcloud users to use OAuth to authenticate to a specific GitLab instance.

## Development

To spin up a local GitLab instance you can run `docker compose up`.
Be aware that the initial start takes a very long time.

Afterward you can log in with the user `root` at `http://localhost` with the password gathered using `docker exec -it gitlab grep 'Password:' /etc/gitlab/initial_root_password`.
Please note that this file is only available on the first start of the container and will be deleted automatically on subsequent runs.

Make sure to set `'allow_local_remote_servers' => true,` in your `config.php` so Nextcloud can access the GitLab instance hosted at `http://localhost`.
