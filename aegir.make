; Aegir Provision makefile
;

core = 7.x
api = 2

projects[drupal][type] = "core"

projects[hostmaster][type] = "profile"
projects[hostmaster][download][type] = "git"
projects[hostmaster][download][url] = "http://git.drupal.org/project/hostmaster.git"
projects[hostmaster][download][branch] = "7.x-3.x"
