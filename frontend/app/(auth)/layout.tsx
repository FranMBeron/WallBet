// (auth) layout — centered card, no sidebar.
// Used by: /login, /register, /connect-wallbit

export default function AuthLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-black px-4">
      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="mb-8 text-center">
          <span className="text-2xl font-bold tracking-tight text-white">
            Wall<span className="text-[#1B6FEB]">Bet</span>
          </span>
        </div>

        {children}
      </div>
    </div>
  );
}
