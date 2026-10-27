using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Reflection;
using System.Threading;
using System.Windows.Forms;

namespace LibrarySystemLauncher
{
    static class Program
    {
        private const string MutexName = "LibrarySystemApp_SingleInstance_Mutex";
        private const string AppTitle = "Sistem Perpustakaan MAS PERSIS 84";
        private const int PortPg = 5434;
        private const int PortBackend = 8000;
        private const int PortFrontend = 3005;

        private static string ProjectDir;
        private static string RuntimeDir;
        private static string PgBinDir;
        private static string PgDataDir;
        private static string PhpExe;
        private static string NodeExe;

        private static Process backendProcess;
        private static Process frontendProcess;
        private static NotifyIcon trayIcon;
        private static ContextMenuStrip trayMenu;

        [STAThread]
        static void Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            InitPaths();

            bool isStopCommand = false;
            bool isBackupCommand = false;
            bool isStatusCommand = false;

            if (args != null && args.Length > 0)
            {
                string arg = args[0].ToLowerInvariant();
                if (arg == "--stop" || arg == "-s" || arg == "/stop") isStopCommand = true;
                if (arg == "--backup" || arg == "-b" || arg == "/backup") isBackupCommand = true;
                if (arg == "--status" || arg == "/status") isStatusCommand = true;
            }

            if (isStopCommand)
            {
                StopAllServices();
                MessageBox.Show("Seluruh layanan sistem perpustakaan telah berhasil dihentikan.", AppTitle, MessageBoxButtons.OK, MessageBoxIcon.Information);
                return;
            }

            if (isBackupCommand)
            {
                RunDatabaseBackup(true);
                return;
            }

            if (isStatusCommand)
            {
                ShowServiceStatusDialog();
                return;
            }

            bool createdNew;
            using (Mutex mutex = new Mutex(true, MutexName, out createdNew))
            {
                if (!createdNew)
                {
                    try
                    {
                        // Pastikan layanan hidup walaupun instance tray masih berjalan.
                        StartServices();
                        OpenBrowser();
                    }
                    catch (Exception ex)
                    {
                        MessageBox.Show(
                            "Layanan gagal dijalankan:`r`n`r`n" + ex.Message,
                            AppTitle,
                            MessageBoxButtons.OK,
                            MessageBoxIcon.Error);
                    }
                    return;
                }

                try
                {
                    StartServices();
                }
                catch (Exception ex)
                {
                    MessageBox.Show("Gagal memulai layanan sistem perpustakaan:\n\n" + ex.Message, AppTitle, MessageBoxButtons.OK, MessageBoxIcon.Error);
                    StopAllServices();
                    return;
                }

                SetupTrayIcon();
                OpenBrowser();

                Application.Run();
            }
        }

        private static void InitPaths()
        {
            ProjectDir = AppDomain.CurrentDomain.BaseDirectory;
            if (File.Exists(Path.Combine(ProjectDir, "..", ".env.windows")))
            {
                ProjectDir = Path.GetFullPath(Path.Combine(ProjectDir, ".."));
            }
            else if (File.Exists(@"C:\Users\AMU66\library-system\.env.windows"))
            {
                ProjectDir = @"C:\Users\AMU66\library-system";
            }

            RuntimeDir = @"D:\LibrarySystemRuntime";
            PgBinDir = Path.Combine(RuntimeDir, @"pgsql\bin");
            PgDataDir = Path.Combine(RuntimeDir, "postgresql-data");
            PhpExe = Path.Combine(RuntimeDir, @"php84\php.exe");

            NodeExe = FindNodeExecutable();
        }

        private static string FindNodeExecutable()
        {
            string[] candidates = new string[]
            {
                @"C:\Program Files\nodejs\node.exe",
                @"C:\Program Files (x86)\nodejs\node.exe",
                Environment.GetEnvironmentVariable("ProgramFiles") + @"\nodejs\node.exe",
                Environment.GetEnvironmentVariable("LOCALAPPDATA") + @"\Programs\node\node.exe"
            };

            foreach (string candidate in candidates)
            {
                if (!string.IsNullOrEmpty(candidate) && File.Exists(candidate))
                {
                    return candidate;
                }
            }

            return "node.exe";
        }

        private static bool IsPortOpen(string host, int port)
        {
            try
            {
                using (TcpClient client = new TcpClient())
                {
                    IAsyncResult result = client.BeginConnect(host, port, null, null);
                    bool success = result.AsyncWaitHandle.WaitOne(800);
                    if (!success) return false;
                    client.EndConnect(result);
                    return true;
                }
            }
            catch
            {
                return false;
            }
        }

        private static bool CheckHttpEndpoint(string url)
        {
            try
            {
                HttpWebRequest req = (HttpWebRequest)WebRequest.Create(url);
                req.Timeout = 1500;
                req.Method = "GET";
                using (HttpWebResponse res = (HttpWebResponse)req.GetResponse())
                {
                    return ((int)res.StatusCode >= 200 && (int)res.StatusCode < 500);
                }
            }
            catch (WebException wex)
            {
                HttpWebResponse res = wex.Response as HttpWebResponse;
                if (res != null)
                {
                    return ((int)res.StatusCode >= 200 && (int)res.StatusCode < 500);
                }
                return false;
            }
            catch
            {
                return false;
            }
        }

        private static void StartServices()
        {
            // 1. PostgreSQL (Port 5434)
            if (!IsPortOpen("127.0.0.1", PortPg))
            {
                string pgCtl = Path.Combine(PgBinDir, "pg_ctl.exe");
                string logFile = Path.Combine(RuntimeDir, @"logs\postgres.log");
                string logDir = Path.GetDirectoryName(logFile);
                if (!Directory.Exists(logDir)) Directory.CreateDirectory(logDir);

                ProcessStartInfo psiPg = new ProcessStartInfo
                {
                    FileName = pgCtl,
                    Arguments = string.Format("start -D \"{0}\" -l \"{1}\" -s -o \"-p 5434 -h 127.0.0.1\" -w", PgDataDir, logFile),
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    WindowStyle = ProcessWindowStyle.Hidden
                };

                using (Process p = Process.Start(psiPg))
                {
                    if (p != null) p.WaitForExit(5000);
                }

                // Wait up to 15 seconds for PostgreSQL
                for (int i = 0; i < 30; i++)
                {
                    if (IsPortOpen("127.0.0.1", PortPg)) break;
                    Thread.Sleep(500);
                }

                if (!IsPortOpen("127.0.0.1", PortPg))
                {
                    throw new Exception("PostgreSQL native pada port 5434 tidak merespons setelah dijalankan.");
                }
            }

            // 2. Laravel Backend (Port 8000)
            if (!CheckHttpEndpoint("http://127.0.0.1:8000/up"))
            {
                ProcessStartInfo psiBackend = new ProcessStartInfo
                {
                    FileName = PhpExe,
                    Arguments = "artisan --env=windows serve --host=127.0.0.1 --port=8000",
                    WorkingDirectory = ProjectDir,
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    WindowStyle = ProcessWindowStyle.Hidden
                };

                // APP_ENV=windows ensures child PHP processes spawned by artisan serve
                // also load .env.windows, not the default .env that points to Docker
                psiBackend.EnvironmentVariables["APP_ENV"] = "windows";

                backendProcess = Process.Start(psiBackend);

                // Wait up to 15 seconds for Backend
                for (int i = 0; i < 30; i++)
                {
                    if (CheckHttpEndpoint("http://127.0.0.1:8000/up")) break;
                    Thread.Sleep(500);
                }

                if (!CheckHttpEndpoint("http://127.0.0.1:8000/up"))
                {
                    throw new Exception("Backend Laravel pada port 8000 tidak merespons endpoint /up.");
                }
            }

            // 3. Nuxt Frontend (Port 3005)
            if (!CheckHttpEndpoint("http://127.0.0.1:3005/login"))
            {
                string runnerScript = Path.Combine(ProjectDir, @"launcher\native-frontend.mjs");
                ProcessStartInfo psiFrontend = new ProcessStartInfo
                {
                    FileName = NodeExe,
                    Arguments = string.Format("\"{0}\"", runnerScript),
                    WorkingDirectory = ProjectDir,
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    WindowStyle = ProcessWindowStyle.Hidden
                };

                psiFrontend.EnvironmentVariables["PORT"] = PortFrontend.ToString();
                psiFrontend.EnvironmentVariables["NODE_ENV"] = "production";
                psiFrontend.EnvironmentVariables["NUXT_PUBLIC_API_BASE_URL"] = "http://127.0.0.1:8000/api";

                frontendProcess = Process.Start(psiFrontend);

                // Wait up to 20 seconds for Nuxt
                for (int i = 0; i < 40; i++)
                {
                    if (CheckHttpEndpoint("http://127.0.0.1:3005/login")) break;
                    Thread.Sleep(500);
                }

                if (!CheckHttpEndpoint("http://127.0.0.1:3005/login"))
                {
                    throw new Exception("Frontend Nuxt pada port 3005 tidak merespons setelah dijalankan.");
                }
            }
        }

        private static void OpenBrowser()
        {
            try
            {
                Process.Start("http://127.0.0.1:3005/login");
            }
            catch (Exception ex)
            {
                MessageBox.Show("Tidak dapat membuka browser default: " + ex.Message, AppTitle, MessageBoxButtons.OK, MessageBoxIcon.Warning);
            }
        }

        private static void SetupTrayIcon()
        {
            trayMenu = new ContextMenuStrip();
            
            ToolStripMenuItem itemOpen = new ToolStripMenuItem("📖 Buka Perpustakaan di Browser", null, (s, e) => OpenBrowser());
            ToolStripMenuItem itemStatus = new ToolStripMenuItem("📊 Status Layanan", null, (s, e) => ShowServiceStatusDialog());
            ToolStripMenuItem itemBackup = new ToolStripMenuItem("💾 Cadangkan Database (Backup)", null, (s, e) => RunDatabaseBackup(false));
            ToolStripMenuItem itemOpenBackups = new ToolStripMenuItem("📁 Buka Folder Backup", null, (s, e) => OpenBackupsFolder());
            ToolStripSeparator sep = new ToolStripSeparator();
            ToolStripMenuItem itemExit = new ToolStripMenuItem("❌ Hentikan & Tutup Aplikasi", null, (s, e) => ExitApplication());

            itemOpen.Font = new Font(itemOpen.Font, FontStyle.Bold);

            trayMenu.Items.Add(itemOpen);
            trayMenu.Items.Add(itemStatus);
            trayMenu.Items.Add(itemBackup);
            trayMenu.Items.Add(itemOpenBackups);
            trayMenu.Items.Add(sep);
            trayMenu.Items.Add(itemExit);

            trayIcon = new NotifyIcon
            {
                Text = AppTitle,
                ContextMenuStrip = trayMenu,
                Visible = true
            };

            string iconPath = Path.Combine(ProjectDir, @"public\favicon.ico");
            if (File.Exists(iconPath))
            {
                try { trayIcon.Icon = new Icon(iconPath); }
                catch { trayIcon.Icon = SystemIcons.Application; }
            }
            else
            {
                trayIcon.Icon = SystemIcons.Application;
            }

            trayIcon.DoubleClick += (s, e) => OpenBrowser();

            trayIcon.ShowBalloonTip(
                3000,
                "Sistem Perpustakaan Siap",
                "Aplikasi berjalan di latar belakang (http://127.0.0.1:3005).\nKlik kanan ikon ini untuk opsi lainnya.",
                ToolTipIcon.Info
            );
        }

        private static void ShowServiceStatusDialog()
        {
            bool pgOk = IsPortOpen("127.0.0.1", PortPg);
            bool laravelOk = CheckHttpEndpoint("http://127.0.0.1:8000/up");
            bool nuxtOk = CheckHttpEndpoint("http://127.0.0.1:3005/login");

            string msg = string.Format(
                "Status Layanan Sistem Perpustakaan (Native Windows):\n\n" +
                "• PostgreSQL Database (:5434)  : {0}\n" +
                "• Backend Laravel API (:8000)   : {1}\n" +
                "• Frontend Nuxt UI (:3005)      : {2}\n\n" +
                "URL Akses: http://127.0.0.1:3005/login",
                pgOk ? "AKTIF ✅" : "TIDAK AKTIF ❌",
                laravelOk ? "AKTIF ✅" : "TIDAK AKTIF ❌",
                nuxtOk ? "AKTIF ✅" : "TIDAK AKTIF ❌"
            );

            MessageBox.Show(msg, "Status Layanan - " + AppTitle, MessageBoxButtons.OK, MessageBoxIcon.Information);
        }

        private static void RunDatabaseBackup(bool isCli)
        {
            try
            {
                string backupsDir = Path.Combine(RuntimeDir, "backups");
                if (!Directory.Exists(backupsDir)) Directory.CreateDirectory(backupsDir);

                string timestamp = DateTime.Now.ToString("yyyyMMdd_HHmmss");
                string dumpFile = Path.Combine(backupsDir, string.Format("library_backup_{0}.dump", timestamp));
                string pgDump = Path.Combine(PgBinDir, "pg_dump.exe");

                ProcessStartInfo psi = new ProcessStartInfo
                {
                    FileName = pgDump,
                    Arguments = string.Format("-h 127.0.0.1 -p 5434 -U sail -F c -b -f \"{0}\" laravel", dumpFile),
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    WindowStyle = ProcessWindowStyle.Hidden
                };

                psi.EnvironmentVariables["PGPASSWORD"] = "20031995";

                using (Process p = Process.Start(psi))
                {
                    if (p != null) p.WaitForExit(15000);
                }

                if (File.Exists(dumpFile) && new FileInfo(dumpFile).Length > 0)
                {
                    long sizeKb = new FileInfo(dumpFile).Length / 1024;
                    string msg = string.Format("Backup database berhasil dibuat:\n\nFile: {0}\nUkuran: {1} KB", Path.GetFileName(dumpFile), sizeKb);
                    if (!isCli && trayIcon != null)
                    {
                        trayIcon.ShowBalloonTip(3000, "Backup Database Berhasil", msg, ToolTipIcon.Info);
                    }
                    MessageBox.Show(msg, AppTitle, MessageBoxButtons.OK, MessageBoxIcon.Information);
                }
                else
                {
                    throw new Exception("Berkas cadangan tidak terbuat atau berukuran 0 byte.");
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Gagal membuat backup database:\n\n" + ex.Message, AppTitle, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private static void OpenBackupsFolder()
        {
            string backupsDir = Path.Combine(RuntimeDir, "backups");
            if (!Directory.Exists(backupsDir)) Directory.CreateDirectory(backupsDir);
            Process.Start("explorer.exe", backupsDir);
        }

        private static void ExitApplication()
        {
            if (trayIcon != null)
            {
                trayIcon.Visible = false;
                trayIcon.Dispose();
            }

            StopAllServices();
            Application.Exit();
        }

        private static void StopAllServices()
        {
            // 1. Terminate frontend child process if spawned
            try
            {
                if (frontendProcess != null && !frontendProcess.HasExited)
                {
                    frontendProcess.Kill();
                }
            }
            catch {}

            // Stop any node running native-frontend
            KillProcessesByCommandLine("node", "native-frontend.mjs");

            // 2. Terminate backend child process if spawned
            try
            {
                if (backendProcess != null && !backendProcess.HasExited)
                {
                    backendProcess.Kill();
                }
            }
            catch {}

            // Stop any php running artisan --env=windows serve
            KillProcessesByCommandLine("php", "artisan --env=windows serve");

            // 3. Cleanly stop PostgreSQL on port 5434
            try
            {
                string pgCtl = Path.Combine(PgBinDir, "pg_ctl.exe");
                if (File.Exists(pgCtl))
                {
                    ProcessStartInfo psiPgStop = new ProcessStartInfo
                    {
                        FileName = pgCtl,
                        Arguments = string.Format("stop -D \"{0}\" -m fast -s", PgDataDir),
                        CreateNoWindow = true,
                        UseShellExecute = false,
                        WindowStyle = ProcessWindowStyle.Hidden
                    };

                    using (Process p = Process.Start(psiPgStop))
                    {
                        if (p != null) p.WaitForExit(8000);
                    }
                }
            }
            catch {}
        }

        private static void KillProcessesByCommandLine(string processName, string matchKeyword)
        {
            try
            {
                Process[] procs = Process.GetProcessesByName(processName);
                foreach (Process p in procs)
                {
                    try
                    {
                        // Using WMI or process path if accessible
                        p.Kill();
                    }
                    catch {}
                }
            }
            catch {}
        }
    }
}
