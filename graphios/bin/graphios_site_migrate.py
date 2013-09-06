
import glob
import os
import os.path
import pwd, grp


class GraphiosMigrate:

    omd_site_root = '/omd/sites'
    omd_skel_dir = '/omd/versions/default/skel'
    
    def get_all_sites(self):
        sites = [os.path.basename(d) for d in glob.glob('%s/*' % self.omd_site_root)]
        return sites
    
    def copy_skel(self, site, skel):
        root = '%s/%s' % (self.omd_site_root, site)
        dest = '%s/%s/%s' % (self.omd_site_root, site, skel)
        skel = '%s/%s' % (self.omd_skel_dir, skel)

	print "Copying and translating %s to %s" % (skel, dest)

        if os.path.exists(dest):
            print "File %s already exists. Skipping." % dest
            return False
        
        with open(skel, 'r') as fh:
            skel_str = fh.read()
        fh.closed
        
        skel_str = skel_str.replace('###ROOT###', root)
        skel_str = skel_str.replace('###SITE###', site)

        dfh = open(dest, 'w')
        dfh.write(skel_str)
        dfh.close()

        uid = pwd.getpwnam(site).pw_uid
        gid = grp.getgrnam(site).gr_gid
        os.chown(dest, uid, gid)

    def is_migrated(self, site):
        return os.path.isdir('%s/%s/var/graphios/spool' % (self.omd_site_root,site))
        
    def do_migration(self):
        all_sites = self.get_all_sites()
        
        for site in all_sites:
            if self.is_migrated(site):
                print "Site %s already enabled. Skipping..." % site
                continue

            print "===> %s <===" % site
            site_dir = '%s/%s' % (self.omd_site_root, site)

            if not os.path.isdir('%s/etc/graphios' % site_dir):
                os.makedirs('%s/etc/graphios' % site_dir)

            skels = ['etc/pnp4nagios/process_perfdata.cfg', 'etc/init.d/graphios', 'etc/graphios/graphios.ini']
            
            for skel in skels:
                print "Copying skeleton %s for site %s" % (skel,site)
                self.copy_skel( site, skel )
                
            os.chmod('%s/etc/init.d/graphios' % site_dir, 0755)

            if not os.path.exists('%s/etc/rc.d/98-graphios' % site_dir):
                print "Creating symlink: %s/etc/init.d/graphios --> %s/etc/rc.d/98-graphios" % (site_dir, site_dir)
                os.symlink('%s/etc/init.d/graphios' % site_dir, '%s/etc/rc.d/98-graphios' % site_dir)

            if not os.path.isdir('%s/var/graphios/spool' % site_dir):
                print "Creating %s/var/graphios/spool" % site_dir
                os.makedirs('%s/var/graphios/spool' % site_dir)

            uid = pwd.getpwnam(site).pw_uid
            gid = grp.getgrnam(site).gr_gid

            os.chown('%s/var/graphios/spool' % site_dir, uid, gid)
            os.chown('%s/etc/rc.d/98-graphios' % site_dir, uid, gid)
            os.chown('%s/etc/graphios' % site_dir, uid, gid)

if __name__ == "__main__":
    print "Migration Tool: Enable graphios support"
    migration = GraphiosMigrate()
    migration.do_migration()
    print "Migration Complete."
